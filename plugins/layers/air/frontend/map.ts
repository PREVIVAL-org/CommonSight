/**
 * Draws the layer `air`: air quality model points as circles in the layer color (K-05).
 */
import type { ItemFeature, LayerMapPart, LayerRenderer } from '@sdk/map';
import { circleLayer, pointGeometry, pointStyle } from '@sdk/map';
export const airLayer: LayerRenderer = {
  toFeatures: (items, context) =>
    items.flatMap((item): ItemFeature[] => {
      const geometry = pointGeometry(item);
      if (item.kind !== 'modelValue' || geometry === null) return [];
      return [
        {
          type: 'Feature',
          geometry,
          properties: {
            itemId: item.id,
            layer: context.layer,
            label: item.title,
            ...pointStyle(null, context.layerColor),
          },
        },
      ];
    }),
  styleLayers: (sourceId) => [circleLayer(sourceId)],
};

/** The map part of the layer air. */
export const map: LayerMapPart = { renderer: airLayer, drawRank: 50 };
