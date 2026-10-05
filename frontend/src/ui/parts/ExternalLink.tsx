/**
 * Link to an external source, opens in a new tab without referrer access (R-01).
 */
import { ExternalLink as ExternalIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function ExternalLink({
  href,
  children,
  className,
}: {
  href: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <a href={href} target="_blank" rel="noopener noreferrer" className={className}>
      {children} <ExternalIcon size={12} aria-hidden="true" focusable="false" />
    </a>
  );
}
