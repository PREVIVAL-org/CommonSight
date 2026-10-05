/**
 * Plugin API of the map for layer packages (plugins/layers/<id>/frontend/map.ts; layers as plugins, L3): what a renderer may
 * use. A package imports only from here and from the contract, never from npm packages or other core modules.
 */
import type { LayerRenderer } from '../map/layers/feature-model';

export type { FilterSpecification, LayerSpecification } from 'maplibre-gl';
export type { Geometry } from 'geojson';
export type {
  Item,
  LayerId,
  Level,
  MeasurementItem,
  ModelValueItem,
  EarthquakeItem,
  TrafficNoticeItem,
  WarningItem,
} from '../contract/types';
export type {
  ItemFeature,
  ItemFeatureProperties,
  LayerRenderer,
  RenderContext,
} from '../map/layers/feature-model';
export { circleLayer, lineLayer } from '../map/layers/circle-layers';
export { drawGeometry, pointGeometry } from '../map/layers/item-geometry';
export { measurementFeatures } from '../map/layers/measurement-features';
export { pointStyle } from '../map/layers/point-style';
export { effectiveLevel } from '../domain/freshness';
export { AWARENESS_COLORS, LEVEL_COLORS, MARKER_HALO, SEVERITY_COLORS } from '../theme/fixed';

/** The map part of a layer package. */
export interface LayerMapPart {
  renderer: LayerRenderer;
  /** Position among the map layers from bottom to top; the region outline of the core lies at 20. */
  drawRank: number;
}
export { warningColor } from '../domain/warning-color';
