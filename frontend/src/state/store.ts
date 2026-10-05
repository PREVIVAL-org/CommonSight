/**
 * Creates the state with zustand/vanilla and provides the actions that change it (V4); no input/output.
 */
import { createStore } from 'zustand/vanilla';
import type { AppState } from './app-state';
import type { DataActions } from './data-actions';
import { createDataActions } from './data-actions';
import type { EnvironmentActions } from './environment-actions';
import { createEnvironmentActions } from './environment-actions';
import type { MapActions } from './map-actions';
import { createMapActions } from './map-actions';
import type { AppStore } from './patch';
import type { SelectionActions } from './selection-actions';
import { createSelectionActions } from './selection-actions';
import type { UiActions } from './ui-actions';
import { createUiActions } from './ui-actions';

export type { AppStore } from './patch';

export type AppActions = SelectionActions & DataActions & EnvironmentActions & MapActions & UiActions;

export interface AppStoreBundle {
  store: AppStore;
  actions: AppActions;
}

export function createAppStore(initial: AppState): AppStoreBundle {
  const store = createStore<AppState>()(() => initial);
  const actions: AppActions = {
    ...createSelectionActions(store),
    ...createDataActions(store),
    ...createEnvironmentActions(store),
    ...createMapActions(store),
    ...createUiActions(store),
  };
  return { store, actions };
}
