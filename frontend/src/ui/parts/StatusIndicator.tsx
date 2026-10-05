/**
 * Shows the status of a layer as an icon with tooltip and screen reader text (U-22, U-31, T-08: shape and
 * color, never color alone).
 */
import * as Tooltip from '@radix-ui/react-tooltip';
import { CircleCheck, CircleX, Clock, TriangleAlert } from 'lucide-react';
import type { Availability } from '../../domain/layer-availability';
import { usePortalContainer, useTexts } from '../hooks';

const ICONS = {
  ok: CircleCheck,
  partial: TriangleAlert,
  setup: TriangleAlert,
  error: CircleX,
  loading: Clock,
  pending: Clock,
} satisfies Record<Availability, typeof CircleCheck>;

export function StatusIndicator({ availability }: { availability: Availability }) {
  const t = useTexts();
  const container = usePortalContainer();
  const label = t.ui(`availability.${availability}`);
  const Icon = ICONS[availability];
  const indicator = (
    <span className="status-indicator" data-availability={availability} role="img" aria-label={label}>
      <Icon size={16} aria-hidden="true" />
    </span>
  );
  if (container === null) return indicator;
  return (
    <Tooltip.Root>
      <Tooltip.Trigger asChild>{indicator}</Tooltip.Trigger>
      <Tooltip.Portal container={container}>
        <Tooltip.Content className="tooltip-content" sideOffset={6}>
          {label}
        </Tooltip.Content>
      </Tooltip.Portal>
    </Tooltip.Root>
  );
}
