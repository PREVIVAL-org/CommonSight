/**
 * Sets the synchronization interval: every 60 s while the tab is visible and online, immediately on return,
 * country switch and changed selection (U-50 to U-55; ADR 0034). The interval starts again with every status
 * fetch, so the next one always follows 60 s after the latest (countdown below the map).
 */
import { isRefreshDue, sameLayers, STATUS_INTERVAL_MS } from '../domain/refresh-policy';
import type { Clock, Timer } from '../infrastructure/timer';
import type { AppState } from '../state/app-state';
import { neededLayersOf, statusReceivedAtMs } from '../state/selectors/data-selectors';
import type { AppStore } from '../state/store';
import type { Synchronizer } from './data-sync';

export { STATUS_INTERVAL_MS };

export class RefreshScheduler {
  private cancelNext: () => void = () => undefined;

  constructor(
    private readonly sync: Synchronizer,
    private readonly store: AppStore,
    private readonly timer: Timer,
    private readonly clock: Clock,
  ) {}

  /** Starts the interval and returns the function that stops it. */
  start(): () => void {
    const unsubscribe = this.store.subscribe((state, previous) => this.react(state, previous));
    this.syncNow();
    return () => {
      this.cancelNext();
      unsubscribe();
      this.sync.abortAll();
    };
  }

  /** Fetches the status now and plans the next fetch one interval later. */
  private syncNow(): void {
    this.planNext();
    void this.sync.syncStatus();
  }

  private planNext(): void {
    this.cancelNext();
    this.cancelNext = this.timer.after(STATUS_INTERVAL_MS, () => this.tick());
  }

  /** Hidden or offline: no fetch, but keep the rhythm; the return triggers the fetch (`returned`). */
  private tick(): void {
    const { env } = this.store.getState();
    if (env.visible && env.online) this.syncNow();
    else this.planNext();
  }

  private react(state: AppState, previous: AppState): void {
    if (state.selection.country !== previous.selection.country) {
      this.sync.abortAll();
      this.syncNow();
    } else if (this.returned(state, previous) || this.borderTurnedOn(state, previous)) {
      this.syncNow();
    } else if (this.neededChanged(state, previous)) {
      void this.sync.syncSnapshots();
    }
  }

  /** Tab visible again with stale data, or connection back. */
  private returned(state: AppState, previous: AppState): boolean {
    if (state.env.online && !previous.env.online) return true;
    if (!state.env.visible || previous.env.visible) return false;
    return isRefreshDue(statusReceivedAtMs(state), this.clock.now(), STATUS_INTERVAL_MS);
  }

  /** Switch "Grenzgebiet" turned on: fetch the status of the neighbours and the border snapshots right away. */
  private borderTurnedOn(state: AppState, previous: AppState): boolean {
    return state.selection.borderZone && !previous.selection.borderZone;
  }

  private neededChanged(state: AppState, previous: AppState): boolean {
    if (state.selection === previous.selection && state.ui.sheet === previous.ui.sheet) return false;
    return !sameLayers(neededLayersOf(state), neededLayersOf(previous));
  }
}
