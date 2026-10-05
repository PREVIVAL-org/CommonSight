/**
 * Describes what a view model needs for preparation: texts, formats, point in time and the layer context.
 */
import type { LayerId } from '../../contract/types';
import type { Formats } from '../../i18n/formats';
import type { Translator } from '../../i18n/translator';

export interface ViewDeps {
  t: Translator;
  format: Formats;
  /** Evaluation time (corrected to server time) for the freshness re-check (B-03). */
  nowMs: number;
}

/** Details of the layer an item comes from. */
export interface LayerContext {
  layer: LayerId;
  /** Display name of the source according to the snapshot; applies to items without their own `source`. */
  source: string;
  /** Country of the items, if they come from another country than the selected one (section "Grenzgebiet"). */
  country?: string;
}
