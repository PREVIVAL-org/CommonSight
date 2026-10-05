/**
 * Shows a short notice such as "2 neue amtliche Warnungen" when an update brings new entries of a layer that asks for
 * it (today warnings), so the automatic update becomes visible. Only in the wide layout; on narrow screens the dot
 * below the map is enough.
 */
import type { VersionedSnapshot } from '../domain/new-entries';
import { newEntryCount } from '../domain/new-entries';
import type { SnapshotKey } from '../domain/snapshot-key';
import { snapshotKeysOf } from '../domain/snapshot-key';
import type { Viewport } from '../infrastructure/viewport';
import { TOAST_SLOTS } from '../domain/layer-slots';
import type { UiActions } from '../state/ui-actions';
import type { AppState } from '../state/app-state';
import type { AppStore } from '../state/store';

type Seen = Partial<Record<SnapshotKey, VersionedSnapshot>>;

/** The snapshots of the selected place that the notices watch. */
function watchedOf(state: AppState): Seen {
  const seen: Seen = {};
  for (const { layer } of TOAST_SLOTS) {
    for (const key of snapshotKeysOf(layer, state.selection.country)) seen[key] = state.data.snapshots[key];
  }
  return seen;
}

/**
 * Compares against the versions seen while the same place was selected: the store keeps the snapshots of a country
 * after a switch, and coming back an hour later would otherwise count everything added meanwhile as new.
 */
export function startNewEntriesNotifier(store: AppStore, actions: UiActions, viewport: Viewport): () => void {
  let seen = watchedOf(store.getState());
  return store.subscribe((state) => {
    const before = seen;
    seen = watchedOf(state);
    if (viewport.isNarrow()) return;
    for (const { layer, part } of TOAST_SLOTS) {
      const count = newEntryCount(before, seen, snapshotKeysOf(layer, state.selection.country));
      if (count > 0) actions.pushMessage({ key: part, params: { count } }, 'info');
    }
  });
}
