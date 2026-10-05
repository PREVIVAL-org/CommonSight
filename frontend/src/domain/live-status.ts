/**
 * Decides whether the data update automatically: connection, outcome and time of the last status fetch of the
 * selected countries (U-50 to U-54).
 */
import type { Country } from '../contract/types';

export type LiveState = 'live' | 'offline' | 'failed' | 'pending';

export interface LiveInputs {
  online: boolean;
  /** Latest status fetch per selected country; `undefined` if none has arrived yet. */
  receivedAtMs: readonly (number | undefined)[];
  /** The latest status fetch of at least one selected country failed. */
  failed: boolean;
}

export function liveStateOf(inputs: LiveInputs): LiveState {
  if (!inputs.online) return 'offline';
  if (inputs.failed) return 'failed';
  return inputs.receivedAtMs.some((ms) => ms === undefined) ? 'pending' : 'live';
}

/** Oldest of the latest status fetches: with "Alle" (all) the state is only as fresh as its oldest country. */
export function dataReceivedAtMs(receivedAtMs: readonly (number | undefined)[]): number | null {
  const known = receivedAtMs.filter((ms): ms is number => ms !== undefined);
  return known.length === 0 ? null : Math.min(...known);
}

export function selectedFailed(selected: readonly Country[], failed: readonly Country[]): boolean {
  return selected.some((country) => failed.includes(country));
}
