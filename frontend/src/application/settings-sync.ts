/**
 * Loads the stored settings at startup and saves changes to the selection or the theme (U-60).
 */
import type { PersistedSettings } from '../domain/persisted-state';
import { parsePersistedSettings } from '../domain/persisted-state';
import type { SettingsStorage } from '../infrastructure/settings-storage';
import type { ThemeMode } from '../theme/theme-mode';
import { parseThemeMode } from '../theme/theme-mode';
import type { AppState } from '../state/app-state';
import type { AppStore } from '../state/store';

export interface StoredSettings {
  settings: PersistedSettings;
  themeChoice: ThemeMode | null;
}

export function loadStoredSettings(storage: SettingsStorage): StoredSettings {
  return {
    settings: parsePersistedSettings(storage.readSettings()),
    themeChoice: parseThemeMode(storage.readTheme()),
  };
}

function persistedPart(state: AppState): PersistedSettings {
  const { country, regionId, activeLayers, mapNoticeHidden, borderZone, mapView } = state.selection;
  return {
    country,
    regionId,
    activeLayers,
    mapNoticeHidden,
    borderZone,
    ...(mapView === null ? {} : { mapView }),
  };
}

const PERSISTED_FIELDS = [
  'country',
  'regionId',
  'activeLayers',
  'mapNoticeHidden',
  'borderZone',
  'mapView',
] as const;

function changed(state: AppState, previous: AppState): boolean {
  return PERSISTED_FIELDS.some((field) => state.selection[field] !== previous.selection[field]);
}

/** Observes the store and writes only when the persisted fields change. */
export function startSettingsSync(storage: SettingsStorage, store: AppStore): () => void {
  return store.subscribe((state, previous) => {
    if (changed(state, previous)) storage.writeSettings(persistedPart(state));
    // "auto" for the system setting: read back, it is no theme and so counts as no choice.
    if (state.env.themeChoice !== previous.env.themeChoice)
      storage.writeTheme(state.env.themeChoice ?? 'auto');
  });
}
