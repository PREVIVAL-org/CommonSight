/**
 * Encapsulates the browser's clock and timers so that the flow control runs with test doubles.
 */

export interface Clock {
  now(): number;
}

export interface Timer {
  /** Calls `callback` every `intervalMs`; the returned function stops the interval. */
  every(intervalMs: number, callback: () => void): () => void;
  /** Calls `callback` once after `delayMs`; the returned function cancels it. */
  after(delayMs: number, callback: () => void): () => void;
}

export const systemClock: Clock = { now: () => Date.now() };

export const systemTimer: Timer = {
  every: (intervalMs, callback) => {
    const handle = setInterval(callback, intervalMs);
    return () => clearInterval(handle);
  },
  after: (delayMs, callback) => {
    const handle = setTimeout(callback, delayMs);
    return () => clearTimeout(handle);
  },
};
