/**
 * Turns a measurement (water gauge, radiation) into the view model with a re-checked assessment
 * (U-80, B-03).
 */
import { catalog, termLabel } from '../../contract/catalog';
import type { MeasurementItem } from '../../contract/types';
import type { AssessmentView } from './assessment-view';
import { toAssessmentView } from './assessment-view';
import type { CardBaseView } from './card-base-view';
import { cardBase, formatTime, layerCategory } from './card-base-view';
import type { FactView } from './fact-view';
import { toFactViews } from './fact-view';
import type { LayerContext, ViewDeps } from './view-deps';

export interface MeasurementView {
  kind: 'measurement';
  base: CardBaseView;
  /** Value with unit, up to 3 decimal places (I-04). */
  value: string;
  reference: string;
  quantity: string;
  assessment: AssessmentView;
  measuredAt: string;
  facts: FactView[];
}

export function toMeasurementView(
  item: MeasurementItem,
  context: LayerContext,
  deps: ViewDeps,
): MeasurementView {
  return {
    kind: 'measurement',
    base: cardBase(item, context, layerCategory(context.layer, deps), deps),
    value: `${deps.format.number(item.value, 3)} ${item.unit}`,
    reference: deps.t.msg(item.reference),
    quantity: termLabel(catalog.measuredQuantities, item.quantity),
    assessment: toAssessmentView(item.assessment, deps),
    measuredAt: deps.t.ui('card.measuredAt', { time: formatTime(item.time, deps) }),
    facts: toFactViews(item.facts, deps),
  };
}
