/**
 * Draws the layer `traffic`: road sections as lines, notice locations as circles (K-05, K-06). Jams in red, larger and
 * on top: the closures and roadworks in the layer colour far outnumber them.
 */
import type { ItemFeature, LayerMapPart, LayerRenderer, RenderContext, TrafficNoticeItem } from '@sdk/map';
import { circleLayer, lineLayer, pointGeometry, pointStyle } from '@sdk/map';

/** Colour of a jam; fixed like the warning colours, not overridable per host page. */
export const JAM_COLOR = '#dc2626';

function style(item: TrafficNoticeItem, context: RenderContext) {
  const base = pointStyle(null, context.layerColor);
  if (item.category !== 'jam') return base;
  return { ...base, color: JAM_COLOR, radius: base.radius + 1, priority: base.priority + 2 };
}

function toFeatures(item: TrafficNoticeItem, context: RenderContext): ItemFeature[] {
  const properties = {
    itemId: item.id,
    layer: context.layer,
    label: item.title,
    ...style(item, context),
  };
  const features: ItemFeature[] = [];
  const line = item.geometry;
  if (line !== undefined && (line.type === 'LineString' || line.type === 'MultiLineString')) {
    features.push({ type: 'Feature', geometry: line, properties });
  }
  const point = pointGeometry(item);
  if (point !== null) features.push({ type: 'Feature', geometry: point, properties });
  return features;
}

export const trafficLayer: LayerRenderer = {
  toFeatures: (items, context) =>
    items.flatMap((item) => (item.kind === 'trafficNotice' ? toFeatures(item, context) : [])),
  styleLayers: (sourceId) => [lineLayer(sourceId), circleLayer(sourceId)],
};

/** The map part of the layer traffic. */
export const map: LayerMapPart = { renderer: trafficLayer, drawRank: 30 };
