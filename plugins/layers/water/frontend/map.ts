/**
 * Draws the layer `water`: water gauges as circles in the assessment color (K-05).
 */
import type { LayerMapPart, LayerRenderer } from '@sdk/map';
import { circleLayer, measurementFeatures } from '@sdk/map';
export const waterLayer: LayerRenderer = {
  toFeatures: measurementFeatures,
  styleLayers: (sourceId) => [circleLayer(sourceId)],
};

/** The map part of the layer water. */
export const map: LayerMapPart = { renderer: waterLayer, drawRank: 70 };
