/**
 * The reference point of the selection for the tiles "Messwerte im Überblick" (U-27).
 */
import type { City, Region } from '../contract/master-data';
import type { LonLat } from './nearest-item';

/** Reference point: the region's reference point, otherwise the first place of the country. */
export function referencePoint(region: Region | null, firstCity: City | null): LonLat | null {
  if (region !== null) return { lon: region.refPoint[0], lat: region.refPoint[1] };
  if (firstCity !== null) return { lon: firstCity.lon, lat: firstCity.lat };
  return null;
}
