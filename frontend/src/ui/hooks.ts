/**
 * Thin hooks for reading state through selectors and for accessing actions and texts; no logic.
 */
import { useContext } from 'react';
import { useStore } from 'zustand';
import type { Translator } from '../i18n/translator';
import type { AppState } from '../state/app-state';
import type { Selectors } from '../state/selectors';
import type { AppActions } from '../state/store';
import type { PageSettings, UiServices } from './services';
import { PageContext, PortalContainerContext, ServicesContext } from './services';

function useServices(): UiServices {
  const services = useContext(ServicesContext);
  if (services === null) throw new Error('ServicesContext missing');
  return services;
}

/** Reads a value through a selector; the selectors are memoized and return stable references. */
export function useAppState<T>(select: (state: AppState, selectors: Selectors) => T): T {
  const { store, selectors } = useServices();
  return useStore(store, (state) => select(state, selectors));
}

export function useActions(): AppActions {
  return useServices().actions;
}

export function useTexts(): Translator {
  return useServices().t;
}

export function usePortalContainer(): HTMLElement | null {
  return useContext(PortalContainerContext);
}

/** Header, logo and access as the host page set them. */
export function usePage(): PageSettings {
  return useContext(PageContext);
}
