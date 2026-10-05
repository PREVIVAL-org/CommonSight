/**
 * Turns a warning into the view model for card, detail sheet and tooltip (U-80, U-83).
 */
import { catalog, termLabel } from '../../contract/catalog';
import type { WarningItem, WarningSection } from '../../contract/types';
import type { BadgeView } from './badge-view';
import type { CardBaseView } from './card-base-view';
import { cardBase } from './card-base-view';
import { truncate } from './truncate';
import type { LayerContext, ViewDeps } from './view-deps';
import { warningColor } from '../warning-color';

export interface WarningSectionView {
  heading: string;
  text: string;
}

export interface WarningView {
  kind: 'warning';
  base: CardBaseView;
  badge: BadgeView;
  hazard: string;
  area: string;
  /** "Gültig ab ... bis ..." (valid from ... until ...) or only start or end; `null` without details. */
  validity: string | null;
  sections: WarningSectionView[];
  /** Compact variant: only the first section, truncated. */
  summary: string | null;
}

const SUMMARY_LENGTH = 180;

function warningBadge(item: WarningItem, deps: ViewDeps): BadgeView {
  const label =
    item.awareness !== undefined
      ? deps.t.ui(`awareness.${item.awareness}`)
      : deps.t.ui(`severity.${item.severity}`);
  return { color: warningColor(item), label, dashed: false };
}

function validity(item: WarningItem, deps: ViewDeps): string | null {
  const from = deps.format.dateTime(item.onset);
  const until = deps.format.dateTime(item.expires);
  if (from !== null && until !== null) return deps.t.ui('card.validity', { from, until });
  if (from !== null) return deps.t.ui('card.validFrom', { from });
  if (until !== null) return deps.t.ui('card.validUntil', { until });
  return null;
}

function sectionView(section: WarningSection, deps: ViewDeps): WarningSectionView {
  return { heading: deps.t.ui(`section.${section.heading}`), text: section.text };
}

export function toWarningView(item: WarningItem, context: LayerContext, deps: ViewDeps): WarningView {
  const first = item.sections[0];
  return {
    kind: 'warning',
    base: cardBase(item, context, termLabel(catalog.warningCategories, item.category), deps),
    badge: warningBadge(item, deps),
    hazard: deps.t.msg(item.hazard),
    area: item.area,
    validity: validity(item, deps),
    sections: item.sections.map((section) => sectionView(section, deps)),
    summary: first === undefined ? null : truncate(first.text, SUMMARY_LENGTH),
  };
}
