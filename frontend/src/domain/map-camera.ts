/**
 * Map section of the last visit (U-60): centre and zoom together with the place they belong to. Restored only if
 * country and region are the same at the next start; another place gets its own fitted view.
 */
import type { CountryChoice } from './country-choice';

export interface MapCamera {
  lon: number;
  lat: number;
  zoom: number;
}

export interface SavedMapView extends MapCamera {
  /** Country and region at the time of saving, see placeKey. */
  place: string;
}

/** Limits of the map (maxBounds and zoom range of the map view); stored values outside are discarded. */
const LIMITS = { west: 0.5, east: 22.5, south: 42.5, north: 58.5, minZoom: 4.5, maxZoom: 18 };

export function placeKey(country: CountryChoice, regionId: string | null): string {
  return `${country}|${regionId ?? ''}`;
}

const round = (value: number, digits: number): number => Number(value.toFixed(digits));

/** About 1 m and 1/100 zoom level: enough for the view, short in storage. */
export function roundCamera(camera: MapCamera): MapCamera {
  return { lon: round(camera.lon, 5), lat: round(camera.lat, 5), zoom: round(camera.zoom, 2) };
}

function within(value: unknown, min: number, max: number): value is number {
  return typeof value === 'number' && Number.isFinite(value) && value >= min && value <= max;
}

export function parseSavedMapView(raw: unknown): SavedMapView | undefined {
  if (typeof raw !== 'object' || raw === null) return undefined;
  const { lon, lat, zoom, place } = raw as Record<string, unknown>;
  if (
    typeof place !== 'string' ||
    !within(lon, LIMITS.west, LIMITS.east) ||
    !within(lat, LIMITS.south, LIMITS.north)
  )
    return undefined;
  if (!within(zoom, LIMITS.minZoom, LIMITS.maxZoom)) return undefined;
  return { place, lon, lat, zoom };
}

/** The saved view if it belongs to this place, otherwise none. */
export function cameraFor(
  saved: SavedMapView | null,
  country: CountryChoice,
  regionId: string | null,
): MapCamera | null {
  return saved !== null && saved.place === placeKey(country, regionId) ? saved : null;
}
