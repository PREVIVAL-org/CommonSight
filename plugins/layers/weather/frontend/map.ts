/**
 * Draws the layer `weather` as a label at the location: place name and rounded temperature (K-04).
 */
import type { ItemFeature, LayerMapPart, LayerRenderer, LayerSpecification, RenderContext } from '@sdk/map';
import { circleLayer, pointGeometry, pointStyle } from '@sdk/map';
/** Short form for the label, e.g. "Wien 18°"; "Modellpunkt" is dropped so the map stays readable. */
export function weatherLabel(title: string, value: number): string {
  return `${title.replace(/^Modellpunkt\s+/, '')} ${Math.round(value)}°`;
}

function labelLayer(sourceId: string, context: RenderContext): LayerSpecification {
  return {
    id: `${sourceId}-labels`,
    type: 'symbol',
    source: sourceId,
    layout: {
      'text-field': ['get', 'label'],
      'text-font': ['Noto Sans Medium'],
      'text-size': 12,
      'text-offset': [0, 0.9],
      'text-anchor': 'top',
      'text-optional': true,
    },
    paint: {
      'text-color': context.labelColor,
      'text-halo-color': context.labelHalo,
      'text-halo-width': 1.4,
    },
  };
}

function styleLayers(sourceId: string, context: RenderContext): LayerSpecification[] {
  return [circleLayer(sourceId), labelLayer(sourceId, context)];
}

export const weatherLayer: LayerRenderer = {
  toFeatures: (items, context) =>
    items.flatMap((item): ItemFeature[] => {
      const geometry = pointGeometry(item);
      if (item.kind !== 'modelValue' || geometry === null) return [];
      const properties = {
        itemId: item.id,
        layer: context.layer,
        label: weatherLabel(item.title, item.value),
        ...pointStyle(null, context.layerColor),
      };
      return [{ type: 'Feature', geometry, properties }];
    }),
  styleLayers,
};

/** The map part of the layer weather. */
export const map: LayerMapPart = { renderer: weatherLayer, drawRank: 80 };
