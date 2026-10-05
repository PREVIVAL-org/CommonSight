/**
 * Draws the layer `radiation`: ambient dose rate measuring stations as circles in the assessment color
 * (K-05, K-11).
 */
import type { LayerMapPart, LayerRenderer } from '@sdk/map';
import { circleLayer, measurementFeatures } from '@sdk/map';
export const radiationLayer: LayerRenderer = {
  toFeatures: measurementFeatures,
  styleLayers: (sourceId) => [circleLayer(sourceId)],
};

/** The map part of the layer radiation. */
export const map: LayerMapPart = { renderer: radiationLayer, drawRank: 60 };
