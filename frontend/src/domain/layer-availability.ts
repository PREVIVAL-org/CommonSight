/**
 * Decides in which state a layer is displayed (U-31, U-35, U-54, A-04).
 */
import type { LayerStatus, Snapshot } from '../contract/types';

export type Availability = 'loading' | 'pending' | 'ok' | 'partial' | 'setup' | 'error';

export interface AvailabilityInputs {
  status: LayerStatus | undefined;
  snapshot: Snapshot | undefined;
  /** Loading the snapshot or the status failed (U-54). */
  loadFailed: boolean;
}

/**
 * The status endpoint knows the state of every layer, even if its snapshot is not loaded
 * (inactive layers, U-50); "Wird geladen" (loading) applies only as long as neither status nor snapshot
 * is present.
 */
export function layerAvailability(inputs: AvailabilityInputs): Availability {
  if (inputs.loadFailed) return 'error';
  const statusValue = inputs.status?.status;
  if (statusValue !== undefined) return statusValue;
  return inputs.snapshot?.status ?? 'loading';
}

/** Connected means: data is present, fully or partially (U-22). */
export function isConnected(availability: Availability): boolean {
  return availability === 'ok' || availability === 'partial';
}
