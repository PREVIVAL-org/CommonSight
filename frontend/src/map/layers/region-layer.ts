/**
 * Describes the outline of the selected region: dashed line and light fill (U-13, Architecture 9.4).
 */
import type { LayerSpecification } from 'maplibre-gl';

export function regionLayers(sourceId: string, color: string): LayerSpecification[] {
  return [
    {
      id: `${sourceId}-fill`,
      type: 'fill',
      source: sourceId,
      paint: { 'fill-color': color, 'fill-opacity': 0.04 },
    },
    {
      id: `${sourceId}-line`,
      type: 'line',
      source: sourceId,
      layout: { 'line-join': 'round' },
      paint: { 'line-color': color, 'line-width': 2.5, 'line-dasharray': [2, 1.5] },
    },
  ];
}
