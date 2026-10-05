/**
 * Determines where the map pans to for an item (K-09): bounding rectangle of the geometry or point.
 */
import type { Geometry, Item, Position } from '../contract/types';

export type BoundingBox = [west: number, south: number, east: number, north: number];

export type FocusTarget = { type: 'bounds'; bbox: BoundingBox } | { type: 'point'; lon: number; lat: number };

function positionsOf(geometry: Geometry): readonly Position[] {
  switch (geometry.type) {
    case 'Point':
      return [geometry.coordinates];
    case 'LineString':
      return geometry.coordinates;
    case 'MultiLineString':
    case 'Polygon':
      return geometry.coordinates.flat();
    case 'MultiPolygon':
      return geometry.coordinates.flat(2);
  }
}

export function boundingBox(positions: readonly Position[]): BoundingBox | null {
  if (positions.length === 0) return null;
  const bbox: BoundingBox = [Infinity, Infinity, -Infinity, -Infinity];
  for (const [lon, lat] of positions) {
    bbox[0] = Math.min(bbox[0], lon);
    bbox[1] = Math.min(bbox[1], lat);
    bbox[2] = Math.max(bbox[2], lon);
    bbox[3] = Math.max(bbox[3], lat);
  }
  return bbox;
}

export function focusTarget(item: Item): FocusTarget | null {
  if (item.geometry !== undefined && item.geometry.type !== 'Point') {
    const bbox = boundingBox(positionsOf(item.geometry));
    if (bbox !== null) return { type: 'bounds', bbox };
  }
  if (item.geometry?.type === 'Point') {
    return { type: 'point', lon: item.geometry.coordinates[0], lat: item.geometry.coordinates[1] };
  }
  if (item.lat !== undefined && item.lon !== undefined)
    return { type: 'point', lon: item.lon, lat: item.lat };
  return null;
}

/** An item has a location if the map can pan to it (U-81). */
export function hasLocation(item: Item): boolean {
  return focusTarget(item) !== null;
}
