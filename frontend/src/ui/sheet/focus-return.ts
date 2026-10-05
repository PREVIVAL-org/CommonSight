/**
 * Returns the focus to the triggering element after the detail sheet closes; in the shadow DOM Radix only sees
 * the host (T-09, Architecture 9.5).
 */
import { useRef } from 'react';

export interface FocusReturnHandlers {
  onOpenAutoFocus(event: Event): void;
  onCloseAutoFocus(event: Event): void;
}

export function useFocusReturn(): FocusReturnHandlers {
  const origin = useRef<HTMLElement | null>(null);
  return {
    onOpenAutoFocus: (event) => {
      const root = (event.currentTarget as Node | null)?.getRootNode() as Document | ShadowRoot | undefined;
      const active = root?.activeElement;
      origin.current = active instanceof HTMLElement ? active : null;
    },
    onCloseAutoFocus: (event) => {
      const target = origin.current;
      if (target === null || !target.isConnected) return;
      event.preventDefault();
      target.focus();
    },
  };
}

/**
 * Keeps the focus in the sheet when its content changes (e.g. "Daten ansehen" opens the sheet of a layer): the button
 * that was activated is gone, so the title of the new content takes the focus. Ref of the title, keyed per sheet.
 */
export function focusIfLost(node: HTMLElement | null): void {
  if (node === null) return;
  const active = (node.getRootNode() as Document | ShadowRoot).activeElement;
  if (active === null || !active.isConnected || active === node.ownerDocument.body) node.focus();
}
