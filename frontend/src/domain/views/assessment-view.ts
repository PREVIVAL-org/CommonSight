/**
 * Prepares an assessment for display after re-checking its freshness (B-03, D-20, D-21).
 */
import type { Assessment, Level } from '../../contract/types';
import { LEVEL_COLORS } from '../../theme/fixed';
import { checkFreshness } from '../freshness';
import type { BadgeView } from './badge-view';
import type { ViewDeps } from './view-deps';

export interface AssessmentView {
  level: Level;
  badge: BadgeView;
  basis: string;
  /** "Letzte Einordnung: ..." (last assessment) when the state is stale, otherwise `null`. */
  previous: string | null;
  origin: string;
  /** Original level of the source (D-21), formatted. */
  sourceValue: string | null;
}

interface Resolved {
  level: Level;
  label: string;
  previous: string | null;
}

function resolve(assessment: Assessment, deps: ViewDeps): Resolved {
  const { t } = deps;
  switch (checkFreshness(assessment, deps.nowMs)) {
    case 'current':
      return { level: assessment.level, label: t.msg(assessment.label), previous: null };
    case 'expired':
      return { level: 'unknown', label: t.ui('assessment.stale'), previous: t.msg(assessment.label) };
    case 'missing':
      if (assessment.level !== 'unknown')
        return { level: 'unknown', label: t.ui('assessment.none'), previous: null };
      return {
        level: 'unknown',
        label: t.msg(assessment.label),
        previous: assessment.previous === undefined ? null : t.msg(assessment.previous.label),
      };
  }
}

export function toAssessmentView(assessment: Assessment, deps: ViewDeps): AssessmentView {
  const resolved = resolve(assessment, deps);
  const { sourceValue } = assessment;
  return {
    level: resolved.level,
    badge: {
      color: LEVEL_COLORS[resolved.level],
      label: resolved.label,
      dashed: resolved.level === 'unknown',
    },
    basis: deps.t.msg(assessment.basis),
    previous: resolved.previous === null ? null : deps.t.ui('card.previous', { label: resolved.previous }),
    origin: deps.t.ui(assessment.origin === 'source' ? 'card.origin.source' : 'card.origin.display'),
    sourceValue:
      sourceValue === undefined ? null : deps.t.ui('card.sourceValue', { value: String(sourceValue) }),
  };
}
