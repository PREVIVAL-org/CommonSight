/**
 * Computes the offset of the browser clock against the server time, so that the age does not depend on
 * clocks that are wrong (Architecture 6.2).
 */

/** Offset in ms that, added to the browser time, gives the server time; 0 without a valid server time. */
export function clockOffsetMs(serverTime: string, receivedAtMs: number): number {
  const server = Date.parse(serverTime);
  return Number.isNaN(server) ? 0 : server - receivedAtMs;
}
