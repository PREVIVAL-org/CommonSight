/**
 * Sets color, size, stroke and order of a measuring point by its assessment (K-05, T-07, Architecture 9.4).
 */
import type { Level } from '../../contract/types';
import { LEVEL_COLORS, MARKER_HALO } from '../../theme/fixed';
import type { ItemFeatureProperties } from './feature-model';

export type PointStyle = Pick<
  ItemFeatureProperties,
  'color' | 'stroke' | 'strokeWidth' | 'radius' | 'opacity' | 'priority'
>;

type Shape = Omit<PointStyle, 'color' | 'stroke'> & { halo: boolean };

/** `high` and `elevated` larger with a light stroke and on top; `unknown` hollow, semi-transparent, bottom. */
const SHAPES: Readonly<Record<Level, Shape>> = {
  high: { strokeWidth: 2, radius: 8, opacity: 1, priority: 4, halo: true },
  elevated: { strokeWidth: 2, radius: 7, opacity: 1, priority: 3, halo: true },
  normal: { strokeWidth: 1, radius: 5, opacity: 0.9, priority: 1, halo: true },
  unknown: { strokeWidth: 1.5, radius: 5, opacity: 0.25, priority: 0, halo: false },
};

/**
 * `null` = item without assessment (layer color). `unknown` is drawn hollow and semi-transparent because
 * MapLibre circles do not support dashed strokes (Architecture 9.4).
 */
export function pointStyle(level: Level | null, layerColor: string): PointStyle {
  const { halo, ...shape } = SHAPES[level ?? 'normal'];
  const color = (level === null ? null : LEVEL_COLORS[level]) ?? layerColor;
  return { ...shape, color, stroke: halo ? MARKER_HALO : color };
}
