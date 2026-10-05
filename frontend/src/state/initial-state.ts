/**
 * Creates the initial state from the host page configuration, stored settings and environment values
 * (U-10, U-20, U-60).
 */
import { LAYER_IDS, layerMeta, layerRegistry } from '../contract/master-data';
import type { LayerId } from '../contract/types';
import type { CountryChoice } from '../domain/country-choice';
import type { PersistedSettings } from '../domain/persisted-state';
import type { ThemeMode } from '../theme/theme-mode';
import type { AppState, EnvironmentState, SelectionState } from './app-state';

/**
 * The first layer of the registry with this list view; the list starts with it. Without one the view has no tab
 * (`LIST_VIEWS`), and any layer stands in so that the selection stays complete.
 */
function firstLayerOf(view: 'measurements' | 'events'): LayerId {
  return LAYER_IDS.find((id) => layerMeta(id).view === view) ?? LAYER_IDS[0] ?? '';
}

export interface EnvironmentSnapshot {
  online: boolean;
  visible: boolean;
  systemDark: boolean;
  nowMs: number;
}

export interface StartConfig {
  country: CountryChoice;
  embedded: boolean;
  themeAttribute: ThemeMode | null;
  themeChoice: ThemeMode | null;
  settings: PersistedSettings;
  environment: EnvironmentSnapshot;
}

export const LIST_PAGE_SIZE = 60;

const DEFAULT_ACTIVE = layerRegistry.filter((layer) => layer.defaultActive).map((layer) => layer.id);

function initialSelection(config: StartConfig): SelectionState {
  const { settings } = config;
  const country = settings.country ?? config.country;
  return {
    country,
    regionId: settings.country === country ? (settings.regionId ?? null) : null,
    view: 'overview',
    activeLayers: settings.activeLayers ?? DEFAULT_ACTIVE,
    listLayers: { measurements: firstLayerOf('measurements'), events: firstLayerOf('events') },
    query: '',
    listLimit: LIST_PAGE_SIZE,
    borderLimit: LIST_PAGE_SIZE,
    tableMode: false,
    newsTopic: 'all',
    mapNoticeHidden: settings.mapNoticeHidden ?? false,
    // The credits of the map behind its "i": closed at every start, not saved.
    attributionHidden: true,
    borderZone: settings.borderZone ?? true,
    mapView: settings.mapView ?? null,
  };
}

function initialEnvironment(config: StartConfig): EnvironmentState {
  const { environment } = config;
  return {
    online: environment.online,
    visible: environment.visible,
    systemDark: environment.systemDark,
    themeAttribute: config.themeAttribute,
    themeChoice: config.themeChoice,
    embedded: config.embedded,
    clockMs: environment.nowMs,
    evaluationMs: environment.nowMs,
  };
}

export function createInitialState(config: StartConfig): AppState {
  return {
    selection: initialSelection(config),
    data: {
      statuses: {},
      statusFailed: [],
      snapshots: {},
      failed: [],
      syncing: false,
      syncStartedAtMs: null,
    },
    env: initialEnvironment(config),
    map: { tilesUrl: undefined, mapError: false, focus: null, outline: { state: 'idle' } },
    ui: {
      sheet: null,
      toasts: [],
      commands: { locate: 0, fullscreen: 0, resetView: 0, retryOutline: 0 },
    },
  };
}
