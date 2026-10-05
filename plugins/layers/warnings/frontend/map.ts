/**
 * Draws the layer `warnings`: warning areas in a fixed warning color by severity or awareness level (K-03, K-06).
 */
import type {
  FilterSpecification,
  ItemFeature,
  LayerMapPart,
  LayerRenderer,
  LayerSpecification,
  RenderContext,
  WarningItem,
} from '@sdk/map';
import { circleLayer, drawGeometry, warningColor } from '@sdk/map';
const SEVERITY_PRIORITY: Record<WarningItem['severity'], number> = {
  Extreme: 4,
  Severe: 3,
  Moderate: 2,
  Minor: 1,
  Unknown: 0,
};

function toFeature(item: WarningItem, context: RenderContext): ItemFeature | null {
  const geometry = drawGeometry(item);
  if (geometry === null) return null;
  const color = warningColor(item);
  return {
    type: 'Feature',
    geometry,
    properties: {
      itemId: item.id,
      layer: context.layer,
      color,
      stroke: color,
      strokeWidth: 3,
      radius: 7,
      opacity: 1,
      priority: SEVERITY_PRIORITY[item.severity],
      label: item.title,
    },
  };
}

const POLYGONS: FilterSpecification = ['in', ['geometry-type'], ['literal', ['Polygon', 'MultiPolygon']]];

/** Fill in the warning color with opacity 0.1 (Architecture 9.4). */
function fillLayer(sourceId: string): LayerSpecification {
  return {
    id: `${sourceId}-fill`,
    type: 'fill',
    source: sourceId,
    filter: POLYGONS,
    layout: { 'fill-sort-key': ['get', 'priority'] },
    paint: { 'fill-color': ['get', 'color'], 'fill-opacity': 0.1 },
  };
}

/** Outline in the warning color with width 3 (Architecture 9.4). */
function outlineLayer(sourceId: string): LayerSpecification {
  return {
    id: `${sourceId}-outline`,
    type: 'line',
    source: sourceId,
    filter: ['!=', ['geometry-type'], 'Point'],
    layout: { 'line-sort-key': ['get', 'priority'] },
    paint: { 'line-color': ['get', 'color'], 'line-width': 3, 'line-opacity': 0.9 },
  };
}

function styleLayers(sourceId: string): LayerSpecification[] {
  return [fillLayer(sourceId), outlineLayer(sourceId), circleLayer(sourceId)];
}

export const warningsLayer: LayerRenderer = {
  toFeatures: (items, context) =>
    items.flatMap((item) => {
      if (item.kind !== 'warning') return [];
      const feature = toFeature(item, context);
      return feature === null ? [] : [feature];
    }),
  styleLayers,
};

/** The map part of the layer warnings. */
export const map: LayerMapPart = { renderer: warningsLayer, drawRank: 10 };
