/**
 * Map-related actions: basemap, map error, camera focus and region outline (K-01, K-02, K-12, U-17).
 */
import type { GeoJSON } from 'geojson';
import type { AppStore } from './patch';
import { patch } from './patch';

export interface MapActions {
  tilesResolved(url: string | null): void;
  mapFailed(): void;
  focusPoint(lon: number, lat: number): void;
  outlineCleared(): void;
  outlineLoading(regionId: string): void;
  outlineLoaded(regionId: string, data: GeoJSON): void;
  outlineFailed(regionId: string): void;
}

/** Only the result for the currently selected region is applied. */
function forCurrentRegion(store: AppStore, regionId: string, apply: () => void): void {
  if (store.getState().selection.regionId === regionId) apply();
}

export function createMapActions(store: AppStore): MapActions {
  return {
    tilesResolved: (tilesUrl) => patch(store, 'map', { tilesUrl }),
    mapFailed: () => patch(store, 'map', { mapError: true }),
    focusPoint: (lon, lat) =>
      patch(store, 'map', (map) => ({
        focus: { seq: (map.focus?.seq ?? 0) + 1, type: 'point' as const, lon, lat },
      })),
    outlineCleared: () => patch(store, 'map', { outline: { state: 'idle' } }),
    outlineLoading: (regionId) => patch(store, 'map', { outline: { state: 'loading', regionId } }),
    outlineLoaded: (regionId, data) =>
      forCurrentRegion(store, regionId, () =>
        patch(store, 'map', { outline: { state: 'ready', regionId, data } }),
      ),
    outlineFailed: (regionId) =>
      forCurrentRegion(store, regionId, () => patch(store, 'map', { outline: { state: 'error', regionId } })),
  };
}
