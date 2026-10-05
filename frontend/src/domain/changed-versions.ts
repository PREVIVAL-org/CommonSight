/**
 * Determines from status and loaded versions the snapshots that need to be reloaded (Architecture 9.3), including
 * those of the border zone (section `border` of the status).
 */
import type { LayerId, LayerStatus, Scope, StatusResponse } from '../contract/types';
import type { SnapshotKey } from './snapshot-key';

export interface SnapshotRef {
  key: SnapshotKey;
  layer: LayerId;
  scope: Scope;
  version: string;
  url: string;
}

/**
 * @param loaded version per snapshot key that is already loaded or currently being loaded
 */
export function changedVersions(
  status: StatusResponse,
  loaded: Readonly<Partial<Record<SnapshotKey, string>>>,
  needed: readonly LayerId[],
  includeBorder = true,
): SnapshotRef[] {
  const refs: SnapshotRef[] = [];
  const add = (layer: LayerId, entry: LayerStatus | undefined): void => {
    if (entry === undefined || entry.version === null || entry.url === null) return;
    const key: SnapshotKey = `${entry.scope}/${layer}`;
    if (loaded[key] === entry.version) return;
    refs.push({ key, layer, scope: entry.scope, version: entry.version, url: entry.url });
  };
  for (const layer of needed) {
    add(layer, status.layers[layer]);
    if (includeBorder) add(layer, status.border?.[layer]);
  }
  return refs;
}
