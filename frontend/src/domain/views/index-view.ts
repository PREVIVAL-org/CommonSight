/**
 * Turns an index (Kp, NOAA scales) into the view model with value on the scale and history (U-80).
 */
import type { IndexItem } from '../../contract/types';
import type { CardBaseView } from './card-base-view';
import { cardBase, layerCategory } from './card-base-view';
import type { LayerContext, ViewDeps } from './view-deps';

export interface IndexView {
  kind: 'index';
  base: CardBaseView;
  name: string;
  /** Abbreviation and value, e.g. "Kp 5". */
  value: string;
  /** Value on the scale, e.g. "5 von 9" (5 of 9). */
  scaleText: string;
  scaleName: string;
  /** Share of the value on the scale between 0 and 1. */
  ratio: number;
  min: number;
  max: number;
  history: number[];
  description: string;
}

function ratioOf(item: IndexItem): number {
  const span = item.scale.max - item.scale.min;
  if (span <= 0) return 0;
  return Math.min(1, Math.max(0, (item.value - item.scale.min) / span));
}

export function toIndexView(item: IndexItem, context: LayerContext, deps: ViewDeps): IndexView {
  const value = deps.format.number(item.value, 2);
  return {
    kind: 'index',
    base: cardBase(item, context, layerCategory(context.layer, deps), deps),
    name: deps.t.msg(item.name),
    value: `${item.title} ${value}`,
    scaleText: deps.t.ui('card.scaleValue', { value, max: item.scale.max }),
    scaleName: deps.t.msg(item.scale.name),
    ratio: ratioOf(item),
    min: item.scale.min,
    max: item.scale.max,
    history: item.history ?? [],
    description: deps.t.msg(item.description),
  };
}
