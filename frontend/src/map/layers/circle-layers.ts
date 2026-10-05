/**
 * Describes the MapLibre layers for points (data-driven circles) and lines of a layer (K-05, K-06, K-11).
 */
import type { LayerSpecification } from 'maplibre-gl';

export function circleLayer(sourceId: string): LayerSpecification {
  return {
    id: `${sourceId}-points`,
    type: 'circle',
    source: sourceId,
    filter: ['==', ['geometry-type'], 'Point'],
    layout: { 'circle-sort-key': ['get', 'priority'] },
    paint: {
      'circle-radius': ['get', 'radius'],
      'circle-color': ['get', 'color'],
      'circle-opacity': ['get', 'opacity'],
      'circle-stroke-color': ['get', 'stroke'],
      'circle-stroke-width': ['get', 'strokeWidth'],
    },
  };
}

export function lineLayer(sourceId: string): LayerSpecification {
  return {
    id: `${sourceId}-lines`,
    type: 'line',
    source: sourceId,
    filter: ['in', ['geometry-type'], ['literal', ['LineString', 'MultiLineString']]],
    layout: { 'line-cap': 'round', 'line-join': 'round' },
    paint: { 'line-color': ['get', 'color'], 'line-width': 3, 'line-opacity': 0.85 },
  };
}
