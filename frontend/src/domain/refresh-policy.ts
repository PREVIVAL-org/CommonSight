/**
 * Decides whether data is reloaded immediately when returning to the tab (U-51).
 */

/** Interval of the status fetch while the tab is visible and online (U-51). */
export const STATUS_INTERVAL_MS = 60_000;

/** Due if nothing has been loaded yet or the last state is at least one interval old. */
export function isRefreshDue(lastReceivedAtMs: number | null, nowMs: number, intervalMs: number): boolean {
  return lastReceivedAtMs === null || nowMs - lastReceivedAtMs >= intervalMs;
}

/** Compares two layer lists for the same entries in the same order. */
export function sameLayers(a: readonly string[], b: readonly string[]): boolean {
  return a.length === b.length && a.every((id, index) => id === b[index]);
}
