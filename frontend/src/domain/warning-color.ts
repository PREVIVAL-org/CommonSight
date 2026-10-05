/**
 * The fixed color of a warning (T-07): its awareness level where the source names one, otherwise its severity. The same
 * on the map and on the card.
 */
import type { WarningItem } from '../contract/types';
import { AWARENESS_COLORS, SEVERITY_COLORS } from '../theme/fixed';

export function warningColor(item: WarningItem): string {
  return item.awareness !== undefined ? AWARENESS_COLORS[item.awareness] : SEVERITY_COLORS[item.severity];
}
