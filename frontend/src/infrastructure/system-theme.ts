/**
 * Reports the system's light/dark setting (`prefers-color-scheme`, T-05).
 */
import type { Unsubscribe } from './page-visibility';

export interface SystemTheme {
  prefersDark(): boolean;
  onChange(listener: (dark: boolean) => void): Unsubscribe;
}

export function createSystemTheme(win: Window): SystemTheme {
  const query = typeof win.matchMedia === 'function' ? win.matchMedia('(prefers-color-scheme: dark)') : null;
  return {
    prefersDark: () => query?.matches ?? false,
    onChange: (listener) => {
      if (query === null) return () => undefined;
      const handler = (event: MediaQueryListEvent): void => listener(event.matches);
      query.addEventListener('change', handler);
      return () => query.removeEventListener('change', handler);
    },
  };
}
