/**
 * Map input/output: the only place that calls MapLibre and pmtiles (map, sources, layers, events, camera;
 * Architecture 9.1).
 */
/* eslint-disable max-lines -- Architecture 9.1 puts all MapLibre calls into this one module; each method has one job (Architecture 1.3.6). */
import type { MapCamera } from '../domain/map-camera';
import maplibreCss from 'maplibre-gl/dist/maplibre-gl.css?inline';
// MapLibre 6 looks for its worker next to its own module; in the bundle it is a separate file (also CSP-safe,
// Architecture 9.4).
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import type { GeoJSON } from 'geojson';
import type {
  LayerSpecification,
  LngLat,
  MapMouseEvent,
  PointLike,
  SourceSpecification,
  StyleSpecification,
} from 'maplibre-gl';
import {
  addProtocol,
  AttributionControl,
  Map as MapLibreMap,
  NavigationControl,
  Popup,
  ScaleControl,
  setWorkerUrl,
} from 'maplibre-gl';
import { Protocol } from 'pmtiles';
import type { BoundingBox } from '../domain/item-location';
import { placeDotImage } from './place-dots';

export const MAPLIBRE_CSS: string = maplibreCss;

export interface MapViewOptions {
  container: HTMLElement;
  bounds: BoundingBox;
  /** Saved section of the last visit; replaces `bounds` at the start. */
  camera?: MapCamera | null;
  maxBounds: BoundingBox;
  /** Labels of the MapLibre controls (K-14). */
  locale: Record<string, string>;
  /** Groups of the data layers from bottom to top. */
  drawOrder: readonly string[];
  /**
   * Touch device: one finger scrolls the page, two fingers move the map (MapLibre cooperative gestures). Off in
   * fullscreen, where there is no page to scroll.
   */
  twoFingerPan: boolean;
}

export interface MapHandlers {
  onItemClick(layer: string, itemId: string): void;
  tooltipFor(layer: string, itemId: string): string | null;
  onError(sourceId: string | null): void;
  /** Attribution collapsed (`true`) or expanded via "i". */
  onAttributionToggle(collapsed: boolean): void;
  /** The map came to rest after a movement (user or program). */
  onMoveEnd(camera: MapCamera): void;
}

export interface DataLayer {
  data: GeoJSON;
  layers: LayerSpecification[];
  attribution?: string;
}

interface Hit {
  layer: string;
  itemId: string;
}

let protocolsRegistered = false;

function registerProtocols(): void {
  if (protocolsRegistered) return;
  setWorkerUrl(workerUrl);
  addProtocol('pmtiles', new Protocol().tile);
  protocolsRegistered = true;
}

const ATTRIBUTION_SHOWN = 'maplibregl-compact-show';

/**
 * Compact attribution that keeps the last chosen state: MapLibre expands it by itself when the first attribution
 * loads; if it is meant to be collapsed, this is reverted immediately. It starts collapsed (K-13: behind the "i").
 * Shown open, it collapses with the first touch or click on the map: MapLibre only does that on a drag, which never
 * comes with two-finger panning on phones, where the open attribution covers most of the map. The state is not saved.
 */
class RememberedAttribution extends AttributionControl {
  collapsed = false;
  onToggle: (collapsed: boolean) => void = () => undefined;
  private container: HTMLElement | null = null;
  private observer: MutationObserver | null = null;
  /** Opened by the user with the "i": stays open until closed the same way. */
  private openedByUser = false;

  constructor() {
    super({ compact: true });
  }

  override onAdd(map: MapLibreMap): HTMLElement {
    const container = super.onAdd(map);
    this.container = container;
    // Capture phase: runs before MapLibre's click handler on the summary. The wish must be known before
    // MapLibre toggles the class; otherwise the observer (a microtask after MapLibre's handler) would
    // collapse the attribution again right after it was opened, and it could never be shown again.
    container.addEventListener(
      'click',
      (event) => {
        if (!(event.target instanceof Element) || event.target.closest('summary') === null) return;
        this.collapsed = container.classList.contains(ATTRIBUTION_SHOWN);
        this.openedByUser = !this.collapsed;
        this.onToggle(this.collapsed);
      },
      true,
    );
    this.observer = new MutationObserver(() => this.apply());
    this.observer.observe(container, { attributes: true, attributeFilter: ['class'] });
    map.getCanvasContainer().addEventListener('pointerdown', () => {
      if (!this.openedByUser) container.classList.remove(ATTRIBUTION_SHOWN);
    });
    return container;
  }

  override onRemove(): void {
    this.observer?.disconnect();
    this.container = null;
    super.onRemove();
  }

  setCollapsed(collapsed: boolean): void {
    this.collapsed = collapsed;
    this.apply();
  }

  private apply(): void {
    if (this.collapsed && this.container?.classList.contains(ATTRIBUTION_SHOWN) === true) {
      this.container.classList.remove(ATTRIBUTION_SHOWN);
    }
  }
}

/**
 * What decides whether a source is built anew instead of only getting new data: its style layers and its credits (when
 * they change, e.g. for another country, MapLibre only takes them from a new source).
 */
function signatureOf(layer: DataLayer): string {
  return JSON.stringify([layer.layers, layer.attribution ?? null]);
}

function sourceOf(layer: DataLayer): SourceSpecification {
  return {
    type: 'geojson',
    data: layer.data,
    ...(layer.attribution === undefined ? {} : { attribution: layer.attribution }),
  };
}

export class MapView {
  private readonly map: MapLibreMap;
  private readonly popup = new Popup({
    closeButton: false,
    closeOnClick: false,
    className: 'cs-tooltip',
    maxWidth: '320px',
    offset: 10,
  });
  private readonly resizeObserver: ResizeObserver;
  private stopFollowingFullscreen: () => void = () => undefined;
  private readonly attribution = new RememberedAttribution();
  private readonly dataLayers = new Map<string, DataLayer>();
  private readonly signatures = new Map<string, string>();
  private readonly drawOrder: readonly string[];
  private handlers: MapHandlers | null = null;
  private baseStyle: StyleSpecification = { version: 8, sources: {}, layers: [] };
  private hovered: string | null = null;
  /**
   * The style can take sources and layers. Not `isStyleLoaded()`: it is also false while tiles load or a `setData` is
   * pending, and the event `style.load` this would wait for only comes with the next complete style rebuild.
   */
  private styleReady = false;

  constructor(options: MapViewOptions) {
    registerProtocols();
    this.drawOrder = options.drawOrder;
    this.map = new MapLibreMap({
      container: options.container,
      style: this.baseStyle,
      ...(options.camera
        ? { center: [options.camera.lon, options.camera.lat] as [number, number], zoom: options.camera.zoom }
        : { bounds: options.bounds }),
      maxBounds: options.maxBounds,
      minZoom: 4.5,
      maxZoom: 18,
      attributionControl: false,
      locale: options.locale,
      dragRotate: false,
      pitchWithRotate: false,
      cooperativeGestures: options.twoFingerPan,
    });
    this.addControls();
    this.provideImages();
    this.listen();
    this.resizeObserver = new ResizeObserver(() => this.map.resize());
    this.resizeObserver.observe(options.container);
    if (options.twoFingerPan) this.followFullscreen(options.container.ownerDocument);
  }

  /**
   * Place dots without a sprite: generated on request, again after every complete style rebuild. Only the resolver can
   * serve the current request; the event `styleimagemissing` comes too late (MapLibre 6.11).
   */
  private provideImages(): void {
    this.map.setMissingStyleImageResolver((id) => {
      const image = placeDotImage(id);
      if (image !== null && !this.map.hasImage(id)) this.map.addImage(id, image, { sdf: true });
    });
  }

  /** Readiness of the style, errors, pointer events for tooltips and details, and the resting map for the saved section (U-60). */
  private listen(): void {
    this.map.on('styledataloading', () => (this.styleReady = false));
    this.map.on('style.load', () => (this.styleReady = true));
    this.map.on('error', (event) =>
      this.handlers?.onError((event as { sourceId?: string }).sourceId ?? null),
    );
    this.map.on('mousemove', (event) => this.hover(event));
    this.map.on('mouseout', () => this.clearHover());
    this.map.on('click', (event) => this.click(event));
    this.map.on('moveend', () => {
      const center = this.map.getCenter();
      this.handlers?.onMoveEnd({ lon: center.lng, lat: center.lat, zoom: this.map.getZoom() });
    });
  }

  private followFullscreen(doc: Document): void {
    const update = (): void => {
      if (doc.fullscreenElement === null) this.map.cooperativeGestures.enable();
      else this.map.cooperativeGestures.disable();
    };
    doc.addEventListener('fullscreenchange', update);
    this.stopFollowingFullscreen = () => doc.removeEventListener('fullscreenchange', update);
  }

  setHandlers(handlers: MapHandlers): void {
    this.handlers = handlers;
    this.attribution.onToggle = (collapsed) => handlers.onAttributionToggle(collapsed);
  }

  /** Applies the attribution state of the selection (collapsed stays collapsed). */
  setAttributionCollapsed(collapsed: boolean): void {
    this.attribution.setCollapsed(collapsed);
  }

  setAriaLabel(label: string): void {
    this.map.getCanvas().setAttribute('aria-label', label);
  }

  /**
   * Replaces the basemap and keeps the data layers (T-04: setStyle with diff). Before the first style has loaded a
   * diff is not possible; MapLibre would warn and rebuild anyway, so it is replaced directly.
   */
  setBaseStyle(style: StyleSpecification): void {
    this.baseStyle = style;
    this.map.setStyle(this.composedStyle(), { diff: this.styleReady });
  }

  /** Creates or updates a data layer; MapLibre layers are only re-attached when their description changed. */
  setDataLayer(group: string, layer: DataLayer): void {
    this.dataLayers.set(group, layer);
    if (this.styleReady) this.applyDataLayer(group);
    // As soon as the style can take the layer, not on 'idle', which waits for every tile and font (2.5 s cold).
    else this.map.once('style.load', () => this.applyDataLayer(group));
  }

  removeDataLayer(group: string): void {
    if (!this.dataLayers.delete(group)) return;
    this.signatures.delete(group);
    this.detach(group);
  }

  fitBounds(bbox: BoundingBox, maxZoom: number): void {
    this.map.fitBounds(bbox, { padding: 32, maxZoom, duration: this.reducedMotion() ? 0 : 600 });
  }

  flyTo(lon: number, lat: number, minZoom: number): void {
    const zoom = Math.max(minZoom, this.map.getZoom());
    this.map.flyTo({ center: [lon, lat], zoom, duration: this.reducedMotion() ? 0 : 900 });
  }

  destroy(): void {
    this.stopFollowingFullscreen();
    this.resizeObserver.disconnect();
    this.popup.remove();
    this.map.remove();
  }

  private addControls(): void {
    this.map.touchZoomRotate.disableRotation();
    this.map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    this.map.addControl(new ScaleControl({ unit: 'metric' }), 'bottom-left');
    this.map.addControl(this.attribution, 'bottom-right');
  }

  private reducedMotion(): boolean {
    return (
      this.map.getContainer().ownerDocument.defaultView?.matchMedia('(prefers-reduced-motion: reduce)')
        .matches ?? false
    );
  }

  private composedStyle(): StyleSpecification {
    const sources = { ...this.baseStyle.sources };
    const layers = [...this.baseStyle.layers];
    for (const group of this.orderedGroups()) {
      const layer = this.dataLayers.get(group);
      if (layer === undefined) continue;
      sources[group] = sourceOf(layer);
      layers.push(...layer.layers);
      this.signatures.set(group, signatureOf(layer));
    }
    return { ...this.baseStyle, sources, layers };
  }

  private orderedGroups(): string[] {
    return [...this.dataLayers.keys()].sort((a, b) => this.rank(a) - this.rank(b));
  }

  private rank(group: string): number {
    const index = this.drawOrder.indexOf(group);
    return index === -1 ? this.drawOrder.length : index;
  }

  private applyDataLayer(group: string): void {
    const layer = this.dataLayers.get(group);
    if (layer === undefined) return;
    const signature = signatureOf(layer);
    const source = this.map.getSource(group) as { setData?: (data: GeoJSON) => void } | undefined;
    if (source?.setData !== undefined && this.signatures.get(group) === signature) {
      source.setData(layer.data);
      return;
    }
    this.detach(group);
    this.map.addSource(group, sourceOf(layer));
    const before = this.firstLayerAbove(group);
    for (const spec of layer.layers) this.map.addLayer(spec, before);
    this.signatures.set(group, signature);
  }

  private detach(group: string): void {
    for (const spec of this.map.getStyle()?.layers ?? []) {
      if ('source' in spec && spec.source === group) this.map.removeLayer(spec.id);
    }
    if (this.map.getSource(group) !== undefined) this.map.removeSource(group);
  }

  private firstLayerAbove(group: string): string | undefined {
    const rank = this.rank(group);
    const next = this.orderedGroups().find(
      (other) => this.rank(other) > rank && this.map.getSource(other) !== undefined,
    );
    return next === undefined ? undefined : this.dataLayers.get(next)?.layers[0]?.id;
  }

  private interactiveLayerIds(): string[] {
    return [...this.dataLayers.entries()]
      .filter(([group]) => group !== 'region')
      .flatMap(([, layer]) => layer.layers.map((spec) => spec.id))
      .filter((id) => this.map.getLayer(id) !== undefined);
  }

  private featureAt(point: PointLike): Hit | null {
    const layers = this.interactiveLayerIds();
    if (layers.length === 0) return null;
    const props = this.map.queryRenderedFeatures(point, { layers })[0]?.properties as
      Partial<Hit> | undefined;
    if (typeof props?.layer !== 'string' || typeof props.itemId !== 'string') return null;
    return { layer: props.layer, itemId: props.itemId };
  }

  private hover(event: MapMouseEvent): void {
    const hit = this.featureAt(event.point);
    if (hit === null) this.clearHover();
    else this.showTooltip(hit, event.lngLat);
  }

  private showTooltip(hit: Hit, at: LngLat): void {
    const html = this.handlers?.tooltipFor(hit.layer, hit.itemId) ?? null;
    if (html === null) return;
    const key = `${hit.layer}/${hit.itemId}`;
    if (this.hovered !== key) this.popup.setHTML(html);
    this.hovered = key;
    this.popup.setLngLat(at).addTo(this.map);
    this.map.getCanvas().style.cursor = 'pointer';
  }

  private clearHover(): void {
    this.hovered = null;
    this.popup.remove();
    this.map.getCanvas().style.cursor = '';
  }

  /** A click opens the detail sheet; on touch devices the first tap opens the tooltip (Architecture 9.4). */
  private click(event: MapMouseEvent): void {
    const hit = this.featureAt(event.point);
    if (hit === null) return;
    const touch = (event.originalEvent as PointerEvent).pointerType === 'touch';
    if (touch && this.hovered !== `${hit.layer}/${hit.itemId}`) {
      this.showTooltip(hit, event.lngLat);
      return;
    }
    this.clearHover();
    this.handlers?.onItemClick(hit.layer, hit.itemId);
  }
}
