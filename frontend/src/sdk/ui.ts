/**
 * Plugin API of the user interface for layer packages (plugins/layers/<id>/frontend/ui.ts; layers as plugins, L3, L4): a
 * layer describes its parts as data and pure functions, the core renders them with its components. Texts are
 * messages of the package (layer.<id>.…) and are translated by the core.
 */
import type { Country, Item, Msg, Snapshot } from '../contract/types';
import type { Availability } from '../domain/layer-availability';
import type { LonLat } from '../domain/nearest-item';

export type {
  Country,
  Item,
  MeasurementItem,
  ModelValueItem,
  Msg,
  RadiationStats,
  Snapshot,
  SpaceStats,
} from '../contract/types';
export type { Availability } from '../domain/layer-availability';
export type { LonLat } from '../domain/nearest-item';
export { nearestItem } from '../domain/nearest-item';

/** What the legend of a layer is computed from. */
export interface LegendInput {
  /** countries of the selection */
  countries: readonly Country[];
  /** the snapshot of the layer for the selection, if loaded */
  snapshot: Snapshot | undefined;
}

/** Meaning of the point colors of a layer (U-25): a title and lines; the first line follows the title. */
export interface LayerLegend {
  title: Msg;
  lines(input: LegendInput): Msg[];
}

/** Formats of the core for values a layer shows, in the language of the page. */
export interface UiFormat {
  number(value: number, digits: number): string;
  /** date and time, "–" without a time */
  time(iso: string | undefined): string;
  text(message: Msg): string;
}

/** What a tile is computed from. */
export interface TileInput {
  /** the snapshot of the layer for the selection, if loaded; with "Alle" the countries merged into one */
  snapshot: Snapshot | undefined;
  /** the items of the selected region */
  matched: readonly Item[];
  /** reference point of the selection: the region's, otherwise the first place of the country */
  reference: LonLat | null;
  nowMs: number;
  format: UiFormat;
}

/** The content of a filled tile, already formatted. */
export interface TileValue {
  value: string;
  detail: string;
  time: string;
  /** small hint below, e.g. "älterer Wert" */
  hint?: Msg;
  highlight?: boolean;
  /** small course of the value (sparkline) from 0 to `max` */
  history?: { values: number[]; max: number; label: Msg };
  /** the item the tile opens; without it, the detail sheet of the layer */
  itemId?: string;
}

/** A tile in "Messwerte im Überblick" (U-27); the tiles are ordered by rank. */
export interface LayerTile {
  rank: number;
  title: Msg;
  /** `null`: no value (the core shows "Wird geladen" or "Keine Daten") */
  value(input: TileInput): TileValue | null;
}

/**
 * The side panel next to the map (U-26), filled with the news items of the layer; at most one, the one with the
 * highest rank.
 */
export interface LayerPanel {
  rank: number;
  title: Msg;
  /** opened instead when the layer has no entries and cannot be reached */
  fallbackLinks: Readonly<Record<Country, string>>;
}

/** What the map notice is computed from (U-23). */
export interface NoticeInput {
  availability: Availability;
  /** the entries of the selected region */
  count: number;
  /** entries without a region, when a region is selected */
  unassigned: number;
  /** name of the selected region, `null` without one */
  regionName: string | null;
  format: UiFormat;
}

export interface NoticeValue {
  tone: 'info' | 'warn' | 'error';
  text: string;
  /** there are entries: the notice offers to show them */
  hasEntries: boolean;
}

/** The notice above the map (U-23); shown is the one with the highest priority. */
export interface LayerNotice {
  priority: number;
  /** accessible label of "Anzeigen" in the notice, which shows the entries of the layer (list view) */
  open: Msg;
  /** button below the layer list that shows the entries of the layer */
  browse: Msg;
  summary(input: NoticeInput): NoticeValue;
}

/** The user interface part of a layer package; everything optional. */
export interface LayerUiPart {
  /** explained in the legend sheet and the detail sheet of the layer; the map shows the link "Legende" when active */
  legend?: LayerLegend;
  /** text of the detail sheet without entries, instead of the general one */
  emptyText?: Msg;
  /** the detail sheet lists the further official information of the country (links.json) */
  officialLinks?: boolean;
  tile?: LayerTile;
  panel?: LayerPanel;
  notice?: LayerNotice;
  /** message with `{count}` shown briefly when an update brings new entries (wide layout only) */
  newEntriesToast?: Msg['key'];
}
