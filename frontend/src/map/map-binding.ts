/**
 * Map flow control: observes the state and passes basemap, data layers, outline and camera on to map-view
 * (Architecture 9.1).
 */
import { attribution, LAYER_IDS, layerMeta, regions } from '../contract/master-data';
import type { LayerId, Snapshot } from '../contract/types';
import { choiceBounds } from '../domain/country-choice';
import type { BoundingBox } from '../domain/item-location';
import { focusTarget } from '../domain/item-location';
import { findRegion } from '../domain/region-options';
import type { Translator } from '../i18n/translator';
import type { AppState } from '../state/app-state';
import { itemOf, layerStatusOf, snapshotOf } from '../state/selectors/data-selectors';
import type { Selectors } from '../state/selectors';
import type { AppActions, AppStore } from '../state/store';
import type { RenderContext } from './layers/feature-model';
import { featureCollection } from './layers/feature-model';
import { regionLayers } from './layers/region-layer';
import { layerRenderers } from './layers/renderers';
import type { MapView } from './map-view';
import type { MapPalette } from './style';
import { BASEMAP_SOURCE, buildBaseStyle, PALETTE_VARIABLES } from './style';
import { tooltipHtml } from './tooltip';
import { attributionHtml } from './attribution';

export { DRAW_ORDER } from './layers/renderers';

export interface MapStyleSource {
  /** Resolves CSS variables to colors (input/output, passed in from outside). */
  resolveColors(names: readonly string[]): Record<string, string>;
  glyphsUrl: string;
  maskUrl: string;
  borderShadeUrl: string;
}

export interface MapBindingDeps {
  store: AppStore;
  actions: AppActions;
  selectors: Selectors;
  view: MapView;
  style: MapStyleSource;
}

const LAYER_VARIABLES = LAYER_IDS.map((id) => `--_layer-${id}`);
const PLACE_MAX_ZOOM = 12;
const FOCUS_BOUNDS_MAX_ZOOM = 11;
const FOCUS_POINT_MIN_ZOOM = 9;
const LOCATE_ZOOM = 10;

function placeBounds(state: AppState): BoundingBox {
  const region = findRegion(regions, state.selection.regionId);
  return region === null ? choiceBounds(state.selection.country) : region.bbox;
}

/** Snapshot of a layer if it is drawn: active and not in status `error` (K-10). */
function drawnSnapshot(state: AppState, layer: LayerId): Snapshot | null {
  if (!state.selection.activeLayers.includes(layer)) return null;
  const snapshot = snapshotOf(state, layer);
  const failed = snapshot?.status === 'error' || layerStatusOf(state, layer)?.status === 'error';
  return snapshot === undefined || failed ? null : snapshot;
}

export class MapBinding {
  private colors: Record<string, string> = {};
  private readonly drawnInputs = new Map<LayerId, readonly unknown[]>();

  constructor(private readonly deps: MapBindingDeps) {}

  start(t: Translator): () => void {
    const { store, view } = this.deps;
    view.setHandlers({
      onItemClick: (layer, itemId) =>
        this.deps.actions.openSheet({ type: 'item', layer: layer as LayerId, itemId }),
      tooltipFor: (layer, itemId) => this.tooltip(layer as LayerId, itemId),
      onError: (sourceId) => this.onError(sourceId),
      onAttributionToggle: (collapsed) => this.deps.actions.setAttributionHidden(collapsed),
      onMoveEnd: (camera) => this.deps.actions.setMapView(camera),
    });
    const state = store.getState();
    view.setAttributionCollapsed(state.selection.attributionHidden);
    this.refreshStyle(state);
    this.updateData(state);
    this.updateOutline(state);
    view.setAriaLabel(t.ui('map.aria', { place: this.deps.selectors.placeName(state) }));
    return store.subscribe((next, previous) => this.react(next, previous, t));
  }

  private react(state: AppState, previous: AppState, t: Translator): void {
    const themeChanged = this.deps.selectors.themeMode(state) !== this.deps.selectors.themeMode(previous);
    if (themeChanged || state.map.tilesUrl !== previous.map.tilesUrl) this.refreshStyle(state);
    this.updateData(state);
    if (themeChanged || state.map.outline !== previous.map.outline) this.updateOutline(state);
    this.updateCamera(state, previous, t);
  }

  private updateCamera(state: AppState, previous: AppState, t: Translator): void {
    const { view, selectors } = this.deps;
    const placeChanged =
      state.selection.country !== previous.selection.country ||
      state.selection.regionId !== previous.selection.regionId;
    if (placeChanged) view.setAriaLabel(t.ui('map.aria', { place: selectors.placeName(state) }));
    if (placeChanged || state.ui.commands.resetView !== previous.ui.commands.resetView)
      view.fitBounds(placeBounds(state), PLACE_MAX_ZOOM);
    if (state.map.focus !== previous.map.focus) this.focus(state);
  }

  private refreshStyle(state: AppState): void {
    this.colors = this.deps.style.resolveColors([...Object.values(PALETTE_VARIABLES), ...LAYER_VARIABLES]);
    this.drawnInputs.clear();
    if (state.map.tilesUrl === undefined) return;
    const palette = Object.fromEntries(
      Object.entries(PALETTE_VARIABLES).map(([key, name]) => [key, this.colors[name] || '#888888']),
    ) as unknown as MapPalette;
    const { style, selectors } = this.deps;
    const mode = selectors.themeMode(state);
    this.deps.view.setBaseStyle(
      buildBaseStyle({
        palette,
        mode,
        tilesUrl: state.map.tilesUrl,
        glyphsUrl: style.glyphsUrl,
        maskUrl: style.maskUrl,
        borderShadeUrl: style.borderShadeUrl,
        attribution: attributionHtml(attribution.basemap),
      }),
    );
  }

  private renderContext(layer: LayerId, nowMs: number): RenderContext {
    return {
      layer,
      layerColor: this.colors[`--_layer-${layer}`] || layerMeta(layer).color,
      labelColor: this.colors[PALETTE_VARIABLES.label] || '#333333',
      labelHalo: this.colors[PALETTE_VARIABLES.labelHalo] || '#ffffff',
      nowMs,
    };
  }

  private updateData(state: AppState): void {
    for (const layer of LAYER_IDS) {
      if (layerRenderers[layer] !== undefined) this.updateLayer(state, layer);
    }
  }

  /** Recomputes the features of a layer only when snapshot, region or assessment time have changed. */
  private updateLayer(state: AppState, layer: LayerId): void {
    const renderer = layerRenderers[layer];
    const snapshot = drawnSnapshot(state, layer);
    if (renderer === undefined || snapshot === null) {
      this.drawnInputs.delete(layer);
      this.deps.view.removeDataLayer(layer);
      return;
    }
    const nowMs = this.deps.selectors.viewDeps(state).nowMs;
    const border = this.deps.selectors.borderAll(state, layer);
    const inputs = [snapshot, state.selection.regionId, nowMs, border];
    const last = this.drawnInputs.get(layer);
    if (last !== undefined && last.every((value, index) => value === inputs[index])) return;
    this.drawnInputs.set(layer, inputs);
    const context = this.renderContext(layer, nowMs);
    this.deps.view.setDataLayer(layer, {
      // DACH items of the selection plus the items of the border area beyond it (vicinityKm) (only on the map, not in the counts).
      data: featureCollection(
        renderer.toFeatures([...this.deps.selectors.scoped(state, layer).matched, ...border], context),
      ),
      // No credits of the sources on the map: they are in the source details of the side bar.
      layers: renderer.styleLayers(layer, context),
    });
  }

  private updateOutline(state: AppState): void {
    const { outline } = state.map;
    const region = outline.state === 'ready' ? findRegion(regions, outline.regionId) : null;
    if (outline.state !== 'ready' || region === null) {
      this.deps.view.removeDataLayer('region');
      return;
    }
    this.deps.view.setDataLayer('region', {
      data: outline.data,
      // No credits on the map: the region borders are credited in the data use of the side bar.
      layers: regionLayers('region', this.colors[PALETTE_VARIABLES.region] || '#1d6cb2'),
    });
  }

  private focus(state: AppState): void {
    const request = state.map.focus;
    if (request === null) return;
    if (request.type === 'point') {
      this.deps.view.flyTo(request.lon, request.lat, LOCATE_ZOOM);
      return;
    }
    const item = itemOf(state, request.layer, request.itemId);
    const target = item === undefined ? null : focusTarget(item);
    if (target?.type === 'bounds') this.deps.view.fitBounds(target.bbox, FOCUS_BOUNDS_MAX_ZOOM);
    else if (target?.type === 'point') this.deps.view.flyTo(target.lon, target.lat, FOCUS_POINT_MIN_ZOOM);
  }

  private tooltip(layer: LayerId, itemId: string): string | null {
    const view = this.deps.selectors.itemView(this.deps.store.getState(), layer, itemId);
    return view === null ? null : tooltipHtml(view);
  }

  /** Basemap errors lead to the message from K-12; missing map assets (mask, glyphs, sprites) have no effect. */
  private onError(sourceId: string | null): void {
    if (sourceId !== BASEMAP_SOURCE || this.deps.store.getState().map.mapError) return;
    this.deps.actions.mapFailed();
    this.deps.actions.pushToast('toast.mapError', 'error');
  }
}
