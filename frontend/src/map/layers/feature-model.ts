/**
 * Describes the features and MapLibre layers that a layer passes to the map; style and tooltip values are in
 * the properties (Architecture 9.4).
 */
import type { Feature, FeatureCollection, Geometry } from 'geojson';
import type { LayerSpecification } from 'maplibre-gl';
import type { Item, LayerId } from '../../contract/types';

export interface ItemFeatureProperties {
  itemId: string;
  layer: LayerId;
  color: string;
  stroke: string;
  strokeWidth: number;
  radius: number;
  opacity: number;
  /** Draw order: higher is on top (K-05). */
  priority: number;
  label: string;
}

export type ItemFeature = Feature<Geometry, ItemFeatureProperties>;
export type ItemFeatureCollection = FeatureCollection<Geometry, ItemFeatureProperties>;

export interface RenderContext {
  layer: LayerId;
  /** Color of the layer from the theming variables (T-06). */
  layerColor: string;
  labelColor: string;
  labelHalo: string;
  nowMs: number;
}

export interface LayerRenderer {
  toFeatures(items: readonly Item[], context: RenderContext): ItemFeature[];
  styleLayers(sourceId: string, context: RenderContext): LayerSpecification[];
}

export function featureCollection(features: ItemFeature[]): ItemFeatureCollection {
  return { type: 'FeatureCollection', features };
}
