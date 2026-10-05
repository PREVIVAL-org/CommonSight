/**
 * Icon-only button, labeled for screen readers and with a tooltip (T-09, Radix Tooltip with a portal in the
 * shadow root).
 */
import * as Tooltip from '@radix-ui/react-tooltip';
import type { ReactNode } from 'react';
import { usePortalContainer } from '../hooks';

interface IconButtonProps {
  label: string;
  onClick: () => void;
  children: ReactNode;
  pressed?: boolean;
}

export function IconButton({ label, onClick, children, pressed }: IconButtonProps) {
  const container = usePortalContainer();
  const button = (
    <button type="button" className="icon-button" aria-label={label} aria-pressed={pressed} onClick={onClick}>
      {children}
    </button>
  );
  if (container === null) return button;
  return (
    <Tooltip.Root>
      <Tooltip.Trigger asChild>{button}</Tooltip.Trigger>
      <Tooltip.Portal container={container}>
        <Tooltip.Content className="tooltip-content" sideOffset={6}>
          {label}
        </Tooltip.Content>
      </Tooltip.Portal>
    </Tooltip.Root>
  );
}
