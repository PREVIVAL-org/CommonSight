/**
 * Shows the status of a layer as a badge with text (U-31, T-08).
 */
import type { Availability } from '../../domain/layer-availability';
import { useTexts } from '../hooks';

export function AvailabilityBadge({ availability }: { availability: Availability }) {
  const t = useTexts();
  return (
    <span className="status" data-availability={availability}>
      {t.ui(`availability.${availability}`)}
    </span>
  );
}
