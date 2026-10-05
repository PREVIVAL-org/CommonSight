/**
 * Map factory: loads MapLibre as a separate chunk in parallel with the UI and connects the map view and
 * state (Architecture 9.7).
 */
import { cameraFor } from './domain/map-camera';
import { choiceBounds } from './domain/country-choice';
import type { Core, ElementConfig, ElementDom } from './entry-types';
import { createColorResolver } from './infrastructure/theme-colors';

import type * as MapViewModule from './map/map-view';
import type * as MapBindingModule from './map/map-binding';
import { adoptStyles } from './infrastructure/adopted-styles';

type ViewModule = typeof MapViewModule;
type BindingModule = typeof MapBindingModule;

/**
 * DACH with the border zone (map-assets/border-zone.geojson, 300 km) and some margin; the map cannot be panned any
 * further (K-01). Widen it together with the border zone.
 */
const DACH_MAX_BOUNDS: [number, number, number, number] = [0.5, 42.5, 22.5, 58.5];

function mapLocale(core: Core): Record<string, string> {
  const { t } = core;
  return {
    'NavigationControl.ZoomIn': t.ui('map.zoomIn'),
    'NavigationControl.ZoomOut': t.ui('map.zoomOut'),
    'AttributionControl.ToggleAttribution': t.ui('map.attribution'),
    'CooperativeGesturesHandler.MobileHelpText': t.ui('map.twoFingers'),
  };
}

/**
 * Deployment of the bundle (directory name below app/), appended to the map areas: they keep their names when
 * rebuilt, and MapLibre loads them in a worker, where browsers keep an old copy despite a forced reload.
 */
function deploymentOf(assetBaseUrl: string): string {
  return encodeURIComponent(/\/app\/([^/]+)\/$/.exec(assetBaseUrl)?.[1] ?? 'dev');
}

/** Builds the map view and binding and returns the function that tears both down again. */
// eslint-disable-next-line max-lines-per-function -- Assembly: each line wires exactly one building block; splitting would only scatter the wiring (Architecture 1.3.3, 1.3.6).
function connectMap(
  modules: [ViewModule, BindingModule],
  core: Core,
  dom: ElementDom,
  config: ElementConfig,
): () => void {
  const [viewModule, bindingModule] = modules;
  const { actions, store } = core.bundle;
  adoptStyles(dom.shadow, 'map', [viewModule.MAPLIBRE_CSS], 'first');
  const { selection } = store.getState();
  const view = new viewModule.MapView({
    container: dom.mapContainer,
    bounds: choiceBounds(store.getState().selection.country),
    // Section of the last visit, if country and region are the same (U-60).
    camera: cameraFor(selection.mapView, selection.country, selection.regionId),
    maxBounds: DACH_MAX_BOUNDS,
    locale: mapLocale(core),
    drawOrder: bindingModule.DRAW_ORDER,
    // Phones and tablets (finger as the main pointer); mouse and touchpad keep moving the map with one gesture.
    twoFingerPan: dom.host.ownerDocument.defaultView?.matchMedia?.('(pointer: coarse)').matches ?? false,
  });
  const colors = createColorResolver(dom.root);
  const release = deploymentOf(config.assetBaseUrl);
  const style = {
    resolveColors: (names: readonly string[]) => colors.resolve(names),
    glyphsUrl: `${config.baseUrl}map/glyphs/{fontstack}/{range}.pbf`,
    maskUrl: `${config.baseUrl}map/dach-mask.geojson?v=${release}`,
    borderShadeUrl: `${config.baseUrl}map/border-shade.geojson?v=${release}`,
  };
  const unsubscribe = new bindingModule.MapBinding({
    store,
    actions,
    selectors: core.selectors,
    view,
    style,
  }).start(core.t);
  return () => {
    unsubscribe();
    view.destroy();
  };
}

export function startMap(core: Core, dom: ElementDom, config: ElementConfig): () => void {
  let stop: (() => void) | null = null;
  let disposed = false;
  void Promise.all([import('./map/map-view'), import('./map/map-binding')])
    .then((modules) => {
      if (!disposed) stop = connectMap(modules, core, dom, config);
    })
    .catch(() => {
      // Without a map (no WebGL, chunk not loadable) the lists remain usable (K-12).
      if (!disposed) core.bundle.actions.mapFailed();
    });
  return () => {
    disposed = true;
    stop?.();
  };
}
