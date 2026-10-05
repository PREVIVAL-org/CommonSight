/**
 * The map notice (U-23) as the core shows it: the summary of the layer with the highest notice priority.
 */
import type { LayerId } from '../../contract/types';
import type { NoticeValue } from '../../sdk/ui';

export interface MapNoticeView extends NoticeValue {
  layer: LayerId;
  /** accessible label of "Anzeigen" */
  openLabel: string;
}
