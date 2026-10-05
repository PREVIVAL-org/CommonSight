/**
 * Keeps theme and embedding mode on the root node in line with the state; runs before the map so that its
 * colors match the theme (T-04, T-05).
 */
import type { RootAttributes } from '../infrastructure/root-attributes';
import type { AppState } from '../state/app-state';
import type { AppStore } from '../state/store';
import { resolveThemeMode } from '../theme/theme-mode';

function valuesOf(state: AppState): { theme: 'light' | 'dark'; embedded: boolean } {
  const { env } = state;
  return {
    theme: resolveThemeMode({
      attribute: env.themeAttribute,
      choice: env.themeChoice,
      systemDark: env.systemDark,
    }),
    embedded: env.embedded,
  };
}

export function startRootAttributeSync(store: AppStore, root: RootAttributes): () => void {
  root.apply(valuesOf(store.getState()));
  return store.subscribe((state, previous) => {
    if (state.env !== previous.env) root.apply(valuesOf(state));
  });
}
