/**
 * Synchronizes the data: fetch the status per selected country, determine changed versions, fetch snapshots,
 * update the store (Architecture 9.3). With "Alle" (all) the three status fetches run in parallel (ADR 0037).
 */
import { COUNTRIES } from '../contract/master-data';
import type { Country, StatusResponse } from '../contract/types';
import type { SnapshotRef } from '../domain/changed-versions';
import { changedVersions } from '../domain/changed-versions';
import { countriesOf } from '../domain/country-choice';
import type { SnapshotKey } from '../domain/snapshot-key';
import { isAbortError } from '../infrastructure/response-errors';
import type { SnapshotApi } from '../infrastructure/snapshot-api';
import type { StatusApi } from '../infrastructure/status-api';
import type { Clock } from '../infrastructure/timer';
import type { DataActions } from '../state/data-actions';
import { loadedVersions, neededLayersOf, selectedStatusResponses } from '../state/selectors/data-selectors';
import type { AppState } from '../state/app-state';
import type { AppStore } from '../state/store';

export interface Synchronizer {
  syncStatus(): Promise<void>;
  syncSnapshots(): Promise<void>;
  abortAll(): void;
}

/** The two interfaces to the server: status and snapshots. */
export interface SyncSources {
  status: StatusApi;
  snapshots: SnapshotApi;
}

/** Country-independent layers appear in every country status; each snapshot is loaded only once. */
function uniqueRefs(refs: readonly SnapshotRef[]): SnapshotRef[] {
  return [...new Map(refs.map((ref) => [ref.key, ref])).values()];
}

/**
 * Countries whose status is fetched: the selection, with the switch "Grenzgebiet" all three, so that the DACH
 * neighbours of the selection are available too (ADR 0038).
 */
function syncedCountries(state: AppState): readonly Country[] {
  return state.selection.borderZone ? COUNTRIES : countriesOf(state.selection.country);
}

function syncedStatusResponses(state: AppState): StatusResponse[] {
  if (!state.selection.borderZone) return selectedStatusResponses(state);
  return COUNTRIES.flatMap((country) => {
    const status = state.data.statuses[country];
    return status === undefined ? [] : [status.response];
  });
}

export class DataSync implements Synchronizer {
  private controller = new AbortController();
  private readonly inFlight = new Map<SnapshotKey, string>();
  private running: Promise<void> | null = null;
  /** The countries the running (or queued) status fetch covers. */
  private runningFor: readonly Country[] = [];
  /** Counts abortAll(): a run queued before it does not start any more. */
  private generation = 0;

  constructor(
    private readonly sources: SyncSources,
    private readonly store: AppStore,
    private readonly actions: DataActions,
    private readonly clock: Clock,
  ) {}

  /**
   * At most one status fetch at a time; a second call waits for the running one. If it wants countries the running one
   * does not cover (e.g. "Grenzgebiet" was just switched on), a fetch for the current selection follows it.
   */
  syncStatus(): Promise<void> {
    const wanted = syncedCountries(this.store.getState());
    if (this.running !== null && wanted.every((country) => this.runningFor.includes(country)))
      return this.running;
    // Without a running fetch it starts at once (its abort signal is the current one); otherwise it follows.
    const generation = this.generation;
    const started =
      this.running === null
        ? this.runStatus()
        : this.running
            .catch(() => undefined)
            .then(() => (generation === this.generation ? this.runStatus() : undefined));
    const run: Promise<void> = started.finally(() => {
      // Only this run clears the guard: after abortAll() a newer run may already hold it.
      if (this.running === run) this.running = null;
    });
    this.running = run;
    this.runningFor = wanted;
    return run;
  }

  async syncSnapshots(): Promise<void> {
    const state = this.store.getState();
    const loaded = { ...loadedVersions(state), ...Object.fromEntries(this.inFlight) };
    const needed = neededLayersOf(state);
    const refs = uniqueRefs(
      syncedStatusResponses(state).flatMap((status) =>
        changedVersions(status, loaded, needed, state.selection.borderZone),
      ),
    );
    const { signal } = this.controller;
    await Promise.all(refs.map((ref) => this.loadSnapshot(ref, signal)));
  }

  /** Aborts running requests, e.g. on a country switch (U-55). */
  abortAll(): void {
    this.generation++;
    this.controller.abort();
    this.controller = new AbortController();
    this.inFlight.clear();
    this.running = null;
    this.actions.setSyncing(false);
  }

  private async runStatus(): Promise<void> {
    const { signal } = this.controller;
    const countries = syncedCountries(this.store.getState());
    this.actions.syncStarted(this.clock.now());
    try {
      await Promise.all(countries.map((country) => this.fetchStatus(country, signal)));
      if (!signal.aborted) await this.syncSnapshots();
    } finally {
      if (!signal.aborted) this.actions.setSyncing(false);
    }
  }

  private async fetchStatus(country: Country, signal: AbortSignal): Promise<void> {
    try {
      const response = await this.sources.status.fetchStatus(country, signal);
      if (!signal.aborted) this.actions.statusReceived(response, this.clock.now());
    } catch (error) {
      // Aborting is intended; any other error makes the status visible as unreachable (U-54).
      if (!isAbortError(error)) this.actions.statusFailed(country);
    }
  }

  private async loadSnapshot(ref: SnapshotRef, signal: AbortSignal): Promise<void> {
    this.inFlight.set(ref.key, ref.version);
    try {
      const snapshot = await this.sources.snapshots.fetchSnapshot(ref.url, signal);
      if (!signal.aborted) this.actions.snapshotLoaded(ref, snapshot);
    } catch (error) {
      // The previous snapshot stays visible, the layer shows the error (U-54); the next interval loads again.
      if (!isAbortError(error)) this.actions.snapshotFailed(ref.key);
    } finally {
      if (this.inFlight.get(ref.key) === ref.version) this.inFlight.delete(ref.key);
    }
  }
}
