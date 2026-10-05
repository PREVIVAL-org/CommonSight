/**
 * Describes the entire state of the element: selection, data, environment, map and UI (Architecture 9.2).
 */
import type { SavedMapView } from '../domain/map-camera';
import type { GeoJSON } from 'geojson';
import type { Country, LayerId, Msg, Snapshot, StatusResponse } from '../contract/types';
import type { CountryChoice } from '../domain/country-choice';
import type { SnapshotKey } from '../domain/snapshot-key';
import type { ListLayers, NewsTopic, SheetState, ViewId } from '../domain/selection';
import type { UiTextKey } from '../i18n/ui-texts.de';
import type { ThemeMode } from '../theme/theme-mode';

export interface SelectionState {
  /** One country or "Alle" (all) (ADR 0037). */
  country: CountryChoice;
  regionId: string | null;
  view: ViewId;
  activeLayers: LayerId[];
  listLayers: ListLayers;
  query: string;
  listLimit: number;
  /** Entries shown in the section "Grenzgebiet" of the list, paged on its own. */
  borderLimit: number;
  tableMode: boolean;
  newsTopic: NewsTopic;
  mapNoticeHidden: boolean;
  attributionHidden: boolean;
  /** Border zone beyond DACH on the map and in the lists (switch "Grenzgebiet", ADR 0038). */
  borderZone: boolean;
  /** Last map section with its place, stored for the next visit (U-60); not used for drawing. */
  mapView: SavedMapView | null;
}

export interface StatusState {
  response: StatusResponse;
  receivedAtMs: number;
  clockOffsetMs: number;
}

export interface LoadedSnapshot {
  version: string;
  snapshot: Snapshot;
}

export interface DataState {
  /** Latest status per country; with "Alle" all three are fetched. */
  statuses: Partial<Record<Country, StatusState>>;
  /** Countries whose latest status fetch failed (U-54). */
  statusFailed: Country[];
  snapshots: Partial<Record<SnapshotKey, LoadedSnapshot>>;
  /** Snapshots whose latest load attempt failed (U-54). */
  failed: SnapshotKey[];
  syncing: boolean;
  /** Browser time at which the latest status fetch started; the next one follows one interval later. */
  syncStartedAtMs: number | null;
}

export interface EnvironmentState {
  online: boolean;
  visible: boolean;
  systemDark: boolean;
  themeAttribute: ThemeMode | null;
  themeChoice: ThemeMode | null;
  embedded: boolean;
  /** Browser time of the clock, every second. */
  clockMs: number;
  /** Browser time of the latest re-evaluation (at least every minute, U-53). */
  evaluationMs: number;
}

export type FocusRequest =
  | { seq: number; type: 'item'; layer: LayerId; itemId: string }
  | { seq: number; type: 'point'; lon: number; lat: number };

export type OutlineState =
  | { state: 'idle' }
  | { state: 'loading'; regionId: string }
  | { state: 'ready'; regionId: string; data: GeoJSON }
  | { state: 'error'; regionId: string };

export interface MapState {
  /** URL of the PMTiles archive; `undefined` = not yet known, `null` = not available (K-12). */
  tilesUrl: string | null | undefined;
  mapError: boolean;
  focus: FocusRequest | null;
  outline: OutlineState;
}

export type CommandName = 'locate' | 'fullscreen' | 'resetView' | 'retryOutline';

/** A short notice: a text of the core with its values, or a message of a layer package. */
export type Toast = {
  id: number;
  tone: 'info' | 'error';
} & (
  | {
      key: UiTextKey;
      /** Values for the placeholders of the text, e.g. `count`. */
      params?: Readonly<Record<string, string | number>>;
    }
  | { message: Msg }
);

export interface UiState {
  sheet: SheetState | null;
  toasts: Toast[];
  /** Counter per command; flow control and map react to every increment. */
  commands: Record<CommandName, number>;
}

export interface AppState {
  selection: SelectionState;
  data: DataState;
  env: EnvironmentState;
  map: MapState;
  ui: UiState;
}
