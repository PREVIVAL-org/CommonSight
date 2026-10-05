/**
 * Validates stored settings and discards invalid values individually (U-60).
 */
import { LAYER_IDS, regions } from '../contract/master-data';
import type { LayerId } from '../contract/types';
import type { CountryChoice } from './country-choice';
import { parseCountryChoice } from './country-choice';
import type { SavedMapView } from './map-camera';
import { parseSavedMapView } from './map-camera';
import { isPersistableLayer } from './layer-slots';

export interface PersistedSettings {
  country?: CountryChoice;
  regionId?: string | null;
  activeLayers?: LayerId[];
  mapNoticeHidden?: boolean;
  /** Show the border zone beyond DACH (switch "Grenzgebiet"). */
  borderZone?: boolean;
  /** Map section of the last visit with its place. */
  mapView?: SavedMapView;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function parseCountry(value: unknown): CountryChoice | undefined {
  return parseCountryChoice(value) ?? undefined;
}

function parseLayers(value: unknown): LayerId[] | undefined {
  if (!Array.isArray(value)) return undefined;
  const known = value.filter((id): id is LayerId => (LAYER_IDS as readonly unknown[]).includes(id));
  return [...new Set(known)].filter(isPersistableLayer);
}

/** With "Alle" there is no region; no region has the country `ALL`. */
function parseRegion(value: unknown, country: CountryChoice | undefined): string | null | undefined {
  if (value === null) return null;
  if (typeof value !== 'string' || country === undefined) return undefined;
  return regions.some((region) => region.id === value && region.country === country) ? value : undefined;
}

/** Reads only what is valid; a region is valid only together with its country. */
export function parsePersistedSettings(raw: unknown): PersistedSettings {
  if (!isRecord(raw)) return {};
  const settings: PersistedSettings = {};
  const country = parseCountry(raw.country);
  const regionId = parseRegion(raw.regionId, country);
  const activeLayers = parseLayers(raw.activeLayers);
  if (country !== undefined) settings.country = country;
  if (regionId !== undefined) settings.regionId = regionId;
  if (activeLayers !== undefined) settings.activeLayers = activeLayers;
  if (typeof raw.mapNoticeHidden === 'boolean') settings.mapNoticeHidden = raw.mapNoticeHidden;
  if (typeof raw.borderZone === 'boolean') settings.borderZone = raw.borderZone;
  const mapView = parseSavedMapView(raw.mapView);
  if (mapView !== undefined) settings.mapView = mapView;
  return settings;
}
