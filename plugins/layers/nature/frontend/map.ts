/**
 * Draws the layer `nature`: earthquakes as circles, sized by magnitude (K-05).
 */
import type { ItemFeature, LayerMapPart, LayerRenderer } from '@sdk/map';
import { circleLayer, pointGeometry, pointStyle } from '@sdk/map';
/** Radius in pixels: grows with the magnitude, limited to 4 to 16. */
export function magnitudeRadius(magnitude: number): number {
  return Math.min(16, Math.max(4, 3 + magnitude * 2));
}

export const natureLayer: LayerRenderer = {
  toFeatures: (items, context) =>
    items.flatMap((item): ItemFeature[] => {
      const geometry = pointGeometry(item);
      if (item.kind !== 'earthquake' || geometry === null) return [];
      const style = {
        ...pointStyle(null, context.layerColor),
        radius: magnitudeRadius(item.magnitude),
        priority: item.magnitude,
      };
      return [
        {
          type: 'Feature',
          geometry,
          properties: { itemId: item.id, layer: context.layer, label: item.title, ...style },
        },
      ];
    }),
  styleLayers: (sourceId) => [circleLayer(sourceId)],
};

/** The map part of the layer nature. */
export const map: LayerMapPart = { renderer: natureLayer, drawRank: 40 };
