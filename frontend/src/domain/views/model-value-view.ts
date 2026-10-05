/**
 * Turns a model value (weather, air) into the view model with the hint "Modellwert, keine Messung"
 * (model value, not a measurement; U-80, Q-WE-02).
 */
import { modelQuantity } from '../../contract/catalog';
import type { ModelValueItem } from '../../contract/types';
import type { CardBaseView } from './card-base-view';
import { cardBase, layerCategory } from './card-base-view';
import type { FactView } from './fact-view';
import { toFactViews } from './fact-view';
import type { LayerContext, ViewDeps } from './view-deps';

export interface ModelValueView {
  kind: 'modelValue';
  base: CardBaseView;
  /** Value with unit and the decimal places of its quantity from the catalog, e.g. temperature 1, AQI none (I-04). */
  value: string;
  /** Rounded value without unit, with the short suffix of its quantity, e.g. for the map label (K-04). */
  shortValue: string;
  summary: string;
  quantity: string;
  facts: FactView[];
  modelHint: string;
}

export function toModelValueView(
  item: ModelValueItem,
  context: LayerContext,
  deps: ViewDeps,
): ModelValueView {
  const quantity = modelQuantity(item.quantity);
  return {
    kind: 'modelValue',
    base: cardBase(item, context, layerCategory(context.layer, deps), deps),
    value: `${deps.format.number(item.value, quantity.decimals)} ${item.unit}`,
    shortValue: `${deps.format.number(Math.round(item.value), 0)}${quantity.shortSuffix}`,
    summary: deps.t.msg(item.summary),
    quantity: quantity.label,
    facts: toFactViews(item.facts, deps),
    modelHint: deps.t.ui('card.modelHint'),
  };
}
