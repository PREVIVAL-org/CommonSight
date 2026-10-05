/**
 * Draws the layer `pollen`: the places as circles in the layer color, labelled with the place and the concentration of
 * the strongest pollen type.
 */
import type { ItemFeature, LayerMapPart, LayerRenderer } from '@sdk/map';
import { circleLayer, pointGeometry, pointStyle } from '@sdk/map';

/** Label of a place: name and rounded pollen concentration, e.g. "Wien 6". */
export function pollenLabel(title: string, value: number): string {
  return `${title} ${Math.round(value)}`;
}

export const pollenLayer: LayerRenderer = {
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
            label: pollenLabel(item.title, item.value),
            ...pointStyle(null, context.layerColor),
          },
        },
      ];
    }),
  styleLayers: (sourceId) => [circleLayer(sourceId)],
};

/** The map part of the layer pollen. */
export const map: LayerMapPart = { renderer: pollenLayer, drawRank: 55 };
