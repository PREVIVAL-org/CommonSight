/**
 * Turns the view model of an item into a row of the table view (U-33).
 */
import type { BadgeView } from './badge-view';
import type { ItemView } from './item-view';
import { assertNever } from './item-view';

export interface TableRowView {
  id: string;
  place: string;
  value: string;
  badge: BadgeView | null;
  time: string;
  source: string;
  url: string;
  hasLocation: boolean;
}

function valueAndBadge(view: ItemView): { value: string; badge: BadgeView | null } {
  switch (view.kind) {
    case 'measurement':
      return { value: view.value, badge: view.assessment.badge };
    case 'modelValue':
      return { value: `${view.value} · ${view.summary}`, badge: null };
    case 'index':
      return { value: view.value, badge: null };
    case 'warning':
      return { value: view.hazard, badge: view.badge };
    case 'earthquake':
      return { value: view.magnitude, badge: null };
    case 'trafficNotice':
      return { value: view.road ?? '', badge: null };
    case 'news':
      return { value: view.topic, badge: null };
    default:
      return assertNever(view);
  }
}

export function toTableRow(view: ItemView): TableRowView {
  const { base } = view;
  return {
    id: base.id,
    place: base.title,
    ...valueAndBadge(view),
    time: base.time,
    source: base.source,
    url: base.url,
    hasLocation: base.hasLocation,
  };
}
