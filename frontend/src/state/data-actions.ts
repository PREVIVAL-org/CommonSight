/**
 * Actions that take over loaded data: status per country, snapshots and load errors (U-52 to U-54).
 */
import type { Country, Snapshot, StatusResponse } from '../contract/types';
import type { SnapshotRef } from '../domain/changed-versions';
import { clockOffsetMs } from '../domain/clock-offset';
import type { SnapshotKey } from '../domain/snapshot-key';
import type { AppStore } from './patch';
import { patch } from './patch';

export interface DataActions {
  statusReceived(response: StatusResponse, receivedAtMs: number): void;
  statusFailed(country: Country): void;
  snapshotLoaded(ref: SnapshotRef, snapshot: Snapshot): void;
  snapshotFailed(key: SnapshotKey): void;
  setSyncing(syncing: boolean): void;
  /** A status fetch starts now (sets `syncing`). */
  syncStarted(atMs: number): void;
}

function statusReceived(store: AppStore, response: StatusResponse, receivedAtMs: number): void {
  const country = response.scope;
  const previous = store.getState().data.statuses[country];
  // If unchanged (304), the response carries the old server time; the last known offset then stays valid.
  const unchanged = previous !== undefined && previous.response === response;
  const offset = unchanged ? previous.clockOffsetMs : clockOffsetMs(response.serverTime, receivedAtMs);
  patch(store, 'data', (data) => ({
    statuses: { ...data.statuses, [country]: { response, receivedAtMs, clockOffsetMs: offset } },
    statusFailed: data.statusFailed.filter((entry) => entry !== country),
  }));
}

function statusFailed(store: AppStore, country: Country): void {
  patch(store, 'data', (data) => ({
    statusFailed: data.statusFailed.includes(country) ? data.statusFailed : [...data.statusFailed, country],
  }));
}

function snapshotLoaded(store: AppStore, ref: SnapshotRef, snapshot: Snapshot): void {
  patch(store, 'data', (data) => ({
    snapshots: { ...data.snapshots, [ref.key]: { version: ref.version, snapshot } },
    failed: data.failed.filter((key) => key !== ref.key),
  }));
}

function snapshotFailed(store: AppStore, key: SnapshotKey): void {
  patch(store, 'data', (data) => ({
    failed: data.failed.includes(key) ? data.failed : [...data.failed, key],
  }));
}

export function createDataActions(store: AppStore): DataActions {
  return {
    statusReceived: (response, receivedAtMs) => statusReceived(store, response, receivedAtMs),
    statusFailed: (country) => statusFailed(store, country),
    snapshotLoaded: (ref, snapshot) => snapshotLoaded(store, ref, snapshot),
    snapshotFailed: (key) => snapshotFailed(store, key),
    setSyncing: (syncing) => patch(store, 'data', { syncing }),
    syncStarted: (atMs) => patch(store, 'data', { syncing: true, syncStartedAtMs: atMs }),
  };
}
