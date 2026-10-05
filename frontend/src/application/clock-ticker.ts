/**
 * Drives the header clock every second and the re-evaluation of age and freshness every 30 s
 * (U-01, U-53, B-03).
 */
import type { Clock, Timer } from '../infrastructure/timer';
import type { EnvironmentActions } from '../state/environment-actions';

export const CLOCK_INTERVAL_MS = 1_000;
export const EVALUATION_INTERVAL_MS = 30_000;

export function startClockTicker(timer: Timer, clock: Clock, actions: EnvironmentActions): () => void {
  const stopClock = timer.every(CLOCK_INTERVAL_MS, () => actions.tickClock(clock.now()));
  const stopEvaluation = timer.every(EVALUATION_INTERVAL_MS, () => actions.tickEvaluation(clock.now()));
  return () => {
    stopClock();
    stopEvaluation();
  };
}
