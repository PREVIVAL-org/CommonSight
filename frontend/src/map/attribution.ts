/**
 * Map credits (K-13): only the base map. The credits of the data sources and of the region borders are not on the
 * map; the data use of the side bar shows them.
 */
import type { AttributionEntry } from '../contract/master-data';
import { escapeHtml } from './tooltip';

/** Text and link are escaped: MapLibre inserts the credits as HTML. */
export function attributionHtml(entry: AttributionEntry): string {
  return `<a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener">${escapeHtml(entry.text)}</a>`;
}
