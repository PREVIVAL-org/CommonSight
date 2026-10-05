/**
 * Counts how many layers are connected or unreachable (U-22 "N von 9 Quellen verbunden").
 */
import type { Availability } from './layer-availability';
import { isConnected } from './layer-availability';

export interface SourceSummary {
  connected: number;
  unreachable: number;
  total: number;
}

export function summarizeSources(availabilities: readonly Availability[]): SourceSummary {
  return {
    connected: availabilities.filter(isConnected).length,
    unreachable: availabilities.filter((availability) => availability === 'error').length,
    total: availabilities.length,
  };
}
