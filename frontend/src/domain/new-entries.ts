/**
 * Counts the entries that a newer version of an already loaded snapshot adds (e.g. new official warnings). The first
 * load of a snapshot (start, country switch) adds nothing, so it does not count as news.
 */
import type { Snapshot } from '../contract/types';
import type { SnapshotKey } from './snapshot-key';

export interface VersionedSnapshot {
  version: string;
  snapshot: Snapshot;
}

type Snapshots = Partial<Record<SnapshotKey, VersionedSnapshot>>;

function addedIds(before: Snapshot, after: Snapshot): number {
  const known = new Set(before.items.map((item) => item.id));
  return after.items.filter((item) => !known.has(item.id)).length;
}

/** @param keys only these snapshots count (the selected countries of one layer, not the DACH neighbours loaded for the border area) */
export function newEntryCount(before: Snapshots, after: Snapshots, keys: readonly SnapshotKey[]): number {
  let count = 0;
  for (const key of keys) {
    const [previous, next] = [before[key], after[key]];
    if (previous === undefined || next === undefined) continue;
    if (previous.version !== next.version) count += addedIds(previous.snapshot, next.snapshot);
  }
  return count;
}
