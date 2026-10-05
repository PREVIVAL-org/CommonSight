/**
 * Builds the common details of every card (header, title, source, location) from an item (U-81).
 */
import type { Item, LayerId } from '../../contract/types';
import { hasLocation } from '../item-location';
import type { LayerContext, ViewDeps } from './view-deps';

export interface CardBaseView {
  id: string;
  layer: LayerId;
  /** Header line: category or layer. */
  category: string;
  /** Measurement or report time, formatted; "kein Datenstand" (no data timestamp) without a time. */
  time: string;
  /** The same time machine-readable (ISO 8601), `null` without one. */
  timeIso: string | null;
  title: string;
  url: string;
  source: string;
  hasLocation: boolean;
  /** Language of the source texts, if different from German (D-15). */
  lang: string | null;
}

export function formatTime(iso: string | undefined, deps: ViewDeps): string {
  return deps.format.dateTime(iso) ?? deps.t.ui('time.none');
}

/** Items of another country with the country, e.g. "Strasbourg (Frankreich)" or "Kufstein (Österreich)". */
function titleOf(item: Item, context: LayerContext, deps: ViewDeps): string {
  const country = item.country ?? context.country;
  return country === undefined ? item.title : `${item.title} (${deps.format.countryName(country)})`;
}

export function cardBase(item: Item, context: LayerContext, category: string, deps: ViewDeps): CardBaseView {
  return {
    id: item.id,
    layer: context.layer,
    category,
    time: formatTime(item.time, deps),
    timeIso: item.time ?? null,
    title: titleOf(item, context, deps),
    url: item.url,
    source: item.source ?? context.source,
    hasLocation: hasLocation(item),
    lang: item.lang !== undefined && item.lang !== 'de' ? item.lang : null,
  };
}

/** Category of a card without a category of its own: the name of the layer. */
export function layerCategory(layer: LayerId, deps: ViewDeps): string {
  return deps.t.layer(layer);
}
