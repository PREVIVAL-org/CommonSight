/**
 * Finds the item closest to a reference point (great-circle distance).
 */
import type { Item } from '../contract/types';

export interface LonLat {
  lon: number;
  lat: number;
}

const EARTH_RADIUS_KM = 6371;

function toRadians(degrees: number): number {
  return (degrees * Math.PI) / 180;
}

export function distanceKm(a: LonLat, b: LonLat): number {
  const dLat = toRadians(b.lat - a.lat);
  const dLon = toRadians(b.lon - a.lon);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRadians(a.lat)) * Math.cos(toRadians(b.lat)) * Math.sin(dLon / 2) ** 2;
  return 2 * EARTH_RADIUS_KM * Math.asin(Math.min(1, Math.sqrt(h)));
}

export function nearestItem<T extends Item>(items: readonly T[], reference: LonLat): T | null {
  let best: T | null = null;
  let bestDistance = Number.POSITIVE_INFINITY;
  for (const item of items) {
    if (item.lat === undefined || item.lon === undefined) continue;
    const distance = distanceKm(reference, { lon: item.lon, lat: item.lat });
    if (distance < bestDistance) {
      best = item;
      bestDistance = distance;
    }
  }
  return best;
}
