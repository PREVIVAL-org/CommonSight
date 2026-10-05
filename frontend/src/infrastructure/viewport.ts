/**
 * Reports whether the narrow (mobile) layout applies; same limit as the media query in theme/layout.css.
 */
export interface Viewport {
  isNarrow(): boolean;
}

const NARROW_QUERY = '(max-width: 860px)';

export function createViewport(win: Window): Viewport {
  const query = typeof win.matchMedia === 'function' ? win.matchMedia(NARROW_QUERY) : null;
  return { isNarrow: () => query?.matches ?? false };
}
