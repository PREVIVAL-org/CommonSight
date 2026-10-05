/**
 * Provides the shared master data from contract/data with types (F-16, Architecture 3.3), and the sources as their
 * plugins describe themselves (contract/catalog/sources.json, generated).
 */
import sourcesJson from '@contract/catalog/sources.json';
import attributionJson from '@contract/data/attribution.json';
import citiesJson from '@contract/data/cities.json';
import countriesJson from '@contract/data/countries.json';
import layersJson from '@contract/catalog/layers.json';
import linksJson from '@contract/data/links.json';
import regionsJson from '@contract/data/regions.json';
import type { Country, LayerId } from './types';

export type ListViewId = 'measurements' | 'events';

export interface LayerMeta {
  id: LayerId;
  scope: 'country' | 'global';
  color: string;
  icon: string;
  onMap: boolean;
  regionFilter: boolean;
  view: ListViewId | null;
  defaultActive: boolean;
  /** Data also from the border zone beyond DACH (scope border). */
  border: boolean;
}

export interface Bounds {
  south: number;
  west: number;
  north: number;
  east: number;
}

export interface CountryMeta {
  name: string;
  flag: string;
  regionTerm: 'state' | 'canton';
  bounds: Bounds;
}

export interface City {
  id: string;
  country: Country;
  name: string;
  lat: number;
  lon: number;
  regionId: string;
}

export interface Region {
  id: string;
  country: Country;
  name: string;
  aliases: string[];
  bbox: [number, number, number, number];
  refPoint: [number, number];
}

export interface OfficialLink {
  label: string;
  url: string;
}

export interface AttributionEntry {
  text: string;
  url: string;
}

/** Attributions that do not come from a source: base map, region boundaries, further notes on data use. */
export interface Attribution {
  basemap: AttributionEntry;
  regions: Record<Country, AttributionEntry & { license: string }>;
  dataUse: (AttributionEntry & { label: string })[];
}

/** A source plugin as it describes itself: the attribution it demands is shown wherever its data appears. */
export interface SourceInfo {
  id: string;
  name: string;
  layer: LayerId;
  /** DE, AT, CH, border or global */
  scopes: string[];
  order: number;
  attribution: AttributionEntry & { license?: string };
}

export const COUNTRIES: readonly Country[] = ['DE', 'AT', 'CH'];
export const layerRegistry = layersJson as readonly LayerMeta[];
export const LAYER_IDS: readonly LayerId[] = layerRegistry.map((layer) => layer.id);
export const countries = countriesJson as Record<Country, CountryMeta>;
export const cities = citiesJson as readonly City[];
export const regions = regionsJson as unknown as readonly Region[];
export const officialLinks = linksJson as Record<Country, OfficialLink[]>;
export const attribution = attributionJson as Attribution;
/** All sources, by layer, then rank. */
export const sources = sourcesJson as readonly SourceInfo[];
/**
 * Interval of a layer until the status names it: the status endpoint reports the same before the first assembly. The
 * interval comes from the sources of a layer (concept: sources as plugins, V1), so the master data have none.
 */
export const UNKNOWN_INTERVAL_SEC = 300;

const layerById = new Map(layerRegistry.map((layer) => [layer.id, layer]));

/** Returns the description of a layer from the registry. */
export function layerMeta(id: LayerId): LayerMeta {
  const meta = layerById.get(id);
  if (meta === undefined) throw new Error(`unknown layer ${id}`);
  return meta;
}
