/**
 * Turns measurements into point features with the assessment re-checked for freshness (K-05, B-03).
 */
import type { Item } from '../../contract/types';
import { effectiveLevel } from '../../domain/freshness';
import type { ItemFeature, RenderContext } from './feature-model';
import { pointGeometry } from './item-geometry';
import { pointStyle } from './point-style';

export function measurementFeatures(items: readonly Item[], context: RenderContext): ItemFeature[] {
  const features: ItemFeature[] = [];
  for (const item of items) {
    if (item.kind !== 'measurement') continue;
    const geometry = pointGeometry(item);
    if (geometry === null) continue;
    const style = pointStyle(effectiveLevel(item.assessment, context.nowMs), context.layerColor);
    features.push({
      type: 'Feature',
      geometry,
      properties: { itemId: item.id, layer: context.layer, label: item.title, ...style },
    });
  }
  return features;
}
