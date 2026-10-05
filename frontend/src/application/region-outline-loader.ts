/**
 * When a region is selected, loads only its outline (one file per region) and reports errors for
 * "Erneut versuchen" (try again) (U-17).
 */
import type { GeoJSON } from 'geojson';
import type { JsonFileApi } from '../infrastructure/json-file-api';
import type { MapActions } from '../state/map-actions';
import type { AppStore } from '../state/store';

function isGeoJson(value: unknown): value is GeoJSON {
  return (
    typeof value === 'object' && value !== null && typeof (value as { type?: unknown }).type === 'string'
  );
}

async function loadOutline(files: JsonFileApi, actions: MapActions, regionId: string): Promise<void> {
  actions.outlineLoading(regionId);
  try {
    const data = await files.fetchJson(`map/regions/${encodeURIComponent(regionId)}.geojson`);
    if (isGeoJson(data)) actions.outlineLoaded(regionId, data);
    else actions.outlineFailed(regionId);
  } catch {
    // The filter stays in effect; the map shows "Erneut versuchen" instead of the outline (U-17).
    actions.outlineFailed(regionId);
  }
}

export function startRegionOutlineLoader(
  files: JsonFileApi,
  store: AppStore,
  actions: MapActions,
): () => void {
  const load = (regionId: string | null): void => {
    if (regionId === null) actions.outlineCleared();
    else void loadOutline(files, actions, regionId);
  };
  load(store.getState().selection.regionId);
  return store.subscribe((state, previous) => {
    const regionChanged = state.selection.regionId !== previous.selection.regionId;
    const retried = state.ui.commands.retryOutline !== previous.ui.commands.retryOutline;
    if (regionChanged || retried) load(state.selection.regionId);
  });
}
