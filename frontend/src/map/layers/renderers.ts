/**
 * Assigns each layer that can be shown on the map its renderer and sets the draw order (K-10, 9.4), from the map parts
 * of the layer packages (layers as plugins, L3).
 */
import { layerMapParts } from '../../generated/layer-maps';
import type { LayerRenderer } from './feature-model';

/** Rank of the region outline of the core among the map parts: above the warning areas, below everything else. */
const REGION_RANK = 20;

export const layerRenderers: Readonly<Partial<Record<string, LayerRenderer>>> = Object.fromEntries(
  Object.entries(layerMapParts).map(([layer, part]) => [layer, part.renderer]),
);

/** Groups of the data layers from bottom to top, by the draw rank of the packages and the region outline. */
export const DRAW_ORDER: readonly string[] = [
  ...Object.entries(layerMapParts).map(([layer, part]) => ({ group: layer, rank: part.drawRank })),
  { group: 'region', rank: REGION_RANK },
]
  .sort((a, b) => a.rank - b.rank || a.group.localeCompare(b.group))
  .map((entry) => entry.group);
