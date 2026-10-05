/**
 * Determines the geometry an item is drawn with: the source's geometry, otherwise a point from lat/lon.
 */
import type { Geometry } from 'geojson';
import type { Item } from '../../contract/types';

export function drawGeometry(item: Item): Geometry | null {
  if (item.geometry !== undefined) return item.geometry;
  if (item.lat !== undefined && item.lon !== undefined)
    return { type: 'Point', coordinates: [item.lon, item.lat] };
  return null;
}

/** Point of an item (for point symbols in addition to a line). */
export function pointGeometry(item: Item): Geometry | null {
  if (item.lat !== undefined && item.lon !== undefined)
    return { type: 'Point', coordinates: [item.lon, item.lat] };
  return item.geometry?.type === 'Point' ? item.geometry : null;
}
