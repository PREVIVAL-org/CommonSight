/**
 * Determines the cause of an empty list (U-35).
 */
import type { Availability } from './layer-availability';

export type EmptyReason = 'setup' | 'error' | 'pending' | 'loading' | 'noMatch' | 'none';

/**
 * @param total items after the region filter, before the text filter
 * @param shown items after the text filter
 */
export function emptyReason(availability: Availability, total: number, shown: number): EmptyReason | null {
  if (shown > 0) return null;
  if (availability === 'setup' || availability === 'pending' || availability === 'loading')
    return availability;
  if (availability === 'error' && total === 0) return 'error';
  return total > 0 ? 'noMatch' : 'none';
}
