/**
 * Builds a store with initial state and optionally loaded snapshots for tests of selectors, orchestration and UI.
 */
import type { Snapshot, StatusResponse } from '../../src/contract/types';
import type { CountryChoice } from '../../src/domain/country-choice';
import type { SnapshotKey } from '../../src/domain/snapshot-key';
import { createInitialState } from '../../src/state/initial-state';
import type { AppStoreBundle } from '../../src/state/store';
import { createAppStore } from '../../src/state/store';
import { NOW } from './deps';

export function createTestStore(country: CountryChoice = 'AT'): AppStoreBundle {
  return createAppStore(
    createInitialState({
      country,
      embedded: false,
      themeAttribute: null,
      themeChoice: null,
      settings: {},
      environment: { online: true, visible: true, systemDark: false, nowMs: NOW },
    }),
  );
}

/** Applies status and snapshots the way data-sync would. */
export function loadInto(bundle: AppStoreBundle, status: StatusResponse | null, snapshots: Snapshot[]): void {
  if (status !== null) bundle.actions.statusReceived(status, NOW);
  for (const snapshot of snapshots) {
    const key = `${snapshot.scope}/${snapshot.layer}` as SnapshotKey;
    bundle.actions.snapshotLoaded(
      { key, layer: snapshot.layer, scope: snapshot.scope, version: 'test0001', url: `data/v1/${key}.json` },
      snapshot,
    );
  }
}
