/**
 * Turns an earthquake into the view model with magnitude, depth and place (U-80).
 */
import type { EarthquakeItem } from '../../contract/types';
import type { CardBaseView } from './card-base-view';
import { cardBase, layerCategory } from './card-base-view';
import type { LayerContext, ViewDeps } from './view-deps';

export interface EarthquakeView {
  kind: 'earthquake';
  base: CardBaseView;
  magnitude: string;
  depth: string;
  place: string;
}

export function toEarthquakeView(
  item: EarthquakeItem,
  context: LayerContext,
  deps: ViewDeps,
): EarthquakeView {
  return {
    kind: 'earthquake',
    base: cardBase(item, context, layerCategory(context.layer, deps), deps),
    magnitude: deps.t.ui('card.magnitudeValue', { value: deps.format.number(item.magnitude, 1) }),
    depth: deps.t.ui('card.depthValue', { value: deps.format.number(item.depthKm, 1) }),
    place: item.place,
  };
}
