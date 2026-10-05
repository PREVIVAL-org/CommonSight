/**
 * Display of the automatic update below the map: a countdown "Aktualisierung in 42 s" (update in 42 s), during the
 * fetch "Wird aktualisiert ..." (updating), otherwise the reason why it pauses, so that nobody thinks they have to
 * reload (U-50 to U-54). The state is told by text and icon shape, never by color alone.
 */
import type { LiveState } from '../live-status';
import type { ViewDeps } from './view-deps';

/** `syncing`: a fetch is running right now. */
export type LiveDisplay = LiveState | 'syncing';

export interface LiveView {
  state: LiveDisplay;
  text: string;
  /** Short form for narrow screens, e.g. "42 s"; empty while the icon alone says it (fetch running, loading). */
  short: string;
  /** Tooltip: what the display means, with the time of the data status. */
  hint: string;
}

/** Whole seconds until the next fetch, never below zero; `null` before the first fetch. */
export function secondsUntil(nextSyncAtMs: number | null, nowMs: number): number | null {
  if (nextSyncAtMs === null) return null;
  return Math.max(0, Math.ceil((nextSyncAtMs - nowMs) / 1000));
}

/** Offline outweighs a running fetch: without a connection nothing updates. */
export function liveDisplayOf(state: LiveState, syncing: boolean): LiveDisplay {
  if (state === 'offline') return 'offline';
  return syncing ? 'syncing' : state;
}

function textOf(display: LiveDisplay, seconds: number | null, deps: ViewDeps): string {
  switch (display) {
    case 'live':
      return seconds === null ? deps.t.ui('live.auto') : deps.t.ui('live.countdown', { seconds });
    case 'failed':
      return seconds === null ? deps.t.ui('live.failed') : deps.t.ui('live.failedRetry', { seconds });
    case 'offline':
      return deps.t.ui('live.offline');
    case 'syncing':
      return deps.t.ui('live.syncing');
    case 'pending':
      return deps.t.ui('live.pending');
  }
}

function shortOf(display: LiveDisplay, seconds: number | null, deps: ViewDeps): string {
  if (display === 'offline') return deps.t.ui('live.offlineShort');
  if ((display === 'live' || display === 'failed') && seconds !== null) {
    return deps.t.ui('live.countdownShort', { seconds });
  }
  return '';
}

function hintOf(receivedAtMs: number | null, deps: ViewDeps): string {
  const time = receivedAtMs === null ? null : deps.format.dateTime(new Date(receivedAtMs).toISOString());
  return time === null ? deps.t.ui('live.hint') : deps.t.ui('live.hintSince', { time });
}

/** `receivedAtMs`: browser time of the latest status, `null` without any. */
export function toLiveView(
  display: LiveDisplay,
  seconds: number | null,
  receivedAtMs: number | null,
  deps: ViewDeps,
): LiveView {
  return {
    state: display,
    text: textOf(display, seconds, deps),
    short: shortOf(display, seconds, deps),
    hint: hintOf(receivedAtMs, deps),
  };
}
