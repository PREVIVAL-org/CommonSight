/**
 * Turns a news item into the view model with topic, time and feed name (U-80); topic and feed names from the catalog.
 */
import { catalog, termLabel } from '../../contract/catalog';
import type { NewsItem } from '../../contract/types';
import type { CardBaseView } from './card-base-view';
import { cardBase } from './card-base-view';
import type { LayerContext, ViewDeps } from './view-deps';

export interface NewsView {
  kind: 'news';
  base: CardBaseView;
  topic: string;
  feed: string;
}

export function toNewsView(item: NewsItem, context: LayerContext, deps: ViewDeps): NewsView {
  const topic = termLabel(catalog.newsCategories, item.category);
  const feed = termLabel(catalog.newsFeeds, item.feed);
  return {
    kind: 'news',
    base: { ...cardBase(item, context, topic, deps), source: item.source ?? feed },
    topic,
    feed,
  };
}
