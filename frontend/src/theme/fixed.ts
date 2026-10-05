/**
 * Holds the domain-defined warning and assessment colors that no theming may change (T-07, K-03, K-05).
 */
import type { Level, Severity } from '../contract/types';

export const SEVERITY_COLORS: Readonly<Record<Severity, string>> = {
  Extreme: '#f66f6f',
  Severe: '#fa9966',
  Moderate: '#e7b567',
  Minor: '#e8c880',
  Unknown: '#e8c880',
};

/** Awareness levels of the source (GeoSphere) mapped to the same fixed colors. */
export const AWARENESS_COLORS: Readonly<Record<'yellow' | 'orange' | 'red', string>> = {
  yellow: SEVERITY_COLORS.Moderate,
  orange: SEVERITY_COLORS.Severe,
  red: SEVERITY_COLORS.Extreme,
};

/** `null` means: use the layer color (no threshold exceeded). */
export const LEVEL_COLORS: Readonly<Record<Level, string | null>> = {
  normal: null,
  elevated: '#f97316',
  high: '#ef4444',
  unknown: '#94a3b8',
};

/** Light stroke of measuring points with an assessment (K-05). */
export const MARKER_HALO = '#ffffff';
