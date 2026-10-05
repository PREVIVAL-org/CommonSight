/**
 * Turns the view model of an item into the escaped HTML content of the map tooltip (K-07, U-83); pure.
 */
import type { ItemView } from '../domain/views/item-view';
import { assertNever } from '../domain/views/item-view';

const ENTITIES: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
};

export function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (char) => ENTITIES[char] ?? char);
}

/** Core details per kind, worded the same as in the box. */
function coreLines(view: ItemView): (string | null)[] {
  switch (view.kind) {
    case 'warning':
      return [`${view.badge.label} · ${view.hazard}`, view.area, view.validity];
    case 'measurement':
      return [
        `${view.value} · ${view.reference}`,
        view.assessment.badge.label,
        view.assessment.basis,
        view.assessment.previous,
      ];
    case 'modelValue':
      return [`${view.value} · ${view.summary}`, view.modelHint];
    case 'earthquake':
      return [`${view.magnitude} · ${view.depth}`, view.place];
    case 'trafficNotice':
      return [view.road, view.noticeType, view.summary];
    case 'index':
      return [`${view.value} · ${view.scaleText}`];
    case 'news':
      return [`${view.topic} · ${view.feed}`];
    default:
      return assertNever(view);
  }
}

export function tooltipHtml(view: ItemView): string {
  const lines = coreLines(view).filter((line): line is string => line !== null && line !== '');
  const body = lines.map((line) => `<div class="cs-tooltip-line">${escapeHtml(line)}</div>`).join('');
  const meta = `${escapeHtml(view.base.source)} · ${escapeHtml(view.base.time)}`;
  return `<div class="cs-tooltip-title">${escapeHtml(view.base.title)}</div>${body}<div class="cs-tooltip-meta">${meta}</div>`;
}
