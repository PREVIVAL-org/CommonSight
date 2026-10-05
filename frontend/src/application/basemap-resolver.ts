/**
 * Determines the current file name of the PMTiles archive via tiles/manifest.json (V9); without a manifest
 * the basemap stays off (K-12).
 */
import type { JsonFileApi } from '../infrastructure/json-file-api';
import type { MapActions } from '../state/map-actions';

function archiveFile(manifest: unknown): string | null {
  if (typeof manifest !== 'object' || manifest === null) return null;
  const file = (manifest as { file?: unknown }).file;
  return typeof file === 'string' && /^[\w.-]+\.pmtiles$/.test(file) ? file : null;
}

export async function resolveBasemap(files: JsonFileApi, actions: MapActions): Promise<void> {
  try {
    const file = archiveFile(await files.fetchJson('tiles/manifest.json'));
    actions.tilesResolved(file === null ? null : files.urlOf(`tiles/${file}`));
  } catch {
    // No manifest means no basemap; the lists remain usable (K-12).
    actions.tilesResolved(null);
  }
}
