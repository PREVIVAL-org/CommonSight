/**
 * Re-checks a supplied assessment against the current time (B-03, Architecture 3.4).
 */
import type { Level } from '../contract/types';

export type Freshness = 'current' | 'expired' | 'missing';

export interface FreshnessInput {
  level: Level;
  validUntil?: string | undefined;
}

/**
 * `missing`: no valid assessment (already `unknown` or without end of validity);
 * `expired`: end of validity reached; otherwise `current`.
 */
export function checkFreshness(assessment: FreshnessInput, nowMs: number): Freshness {
  if (assessment.level === 'unknown' || assessment.validUntil === undefined) return 'missing';
  const validUntil = Date.parse(assessment.validUntil);
  if (Number.isNaN(validUntil)) return 'missing';
  return nowMs >= validUntil ? 'expired' : 'current';
}

/** Level that is displayed: only a current assessment keeps its level, otherwise `unknown`. */
export function effectiveLevel(assessment: FreshnessInput, nowMs: number): Level {
  return checkFreshness(assessment, nowMs) === 'current' ? assessment.level : 'unknown';
}
