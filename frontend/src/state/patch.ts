/**
 * Changes a section of the state immutably so that selectors detect changes by reference.
 */
import type { StoreApi } from 'zustand/vanilla';
import type { AppState } from './app-state';

export type AppStore = StoreApi<AppState>;

export function patch<K extends keyof AppState>(
  store: AppStore,
  section: K,
  change: Partial<AppState[K]> | ((current: AppState[K]) => Partial<AppState[K]>),
): void {
  store.setState((state) => {
    const current = state[section];
    const delta = typeof change === 'function' ? change(current) : change;
    return { [section]: { ...current, ...delta } } as Pick<AppState, K>;
  });
}
