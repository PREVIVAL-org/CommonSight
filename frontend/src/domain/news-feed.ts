/**
 * Assembles the news of a country: topic filter, newest first, on a tie the country's feed order
 * (Q-NE-02, U-26). The feed order follows the catalog: feeds of the country first, then the others.
 */
import type { Item, NewsFeed, NewsItem } from '../contract/types';
import type { NewsTopic } from './selection';

function timeOf(item: NewsItem): number {
  const at = item.time === undefined ? Number.NaN : Date.parse(item.time);
  return Number.isNaN(at) ? 0 : at;
}

/** Feed order of a country: its own feeds first, then the others, each in catalog order. */
export function feedOrder(feeds: readonly { id: string; country: string }[], country: string): NewsFeed[] {
  const own = feeds.filter((feed) => feed.country === country);
  const others = feeds.filter((feed) => feed.country !== country);
  return [...own, ...others].map((feed) => feed.id as NewsFeed);
}

export function newsFeed(
  items: readonly Item[],
  topic: NewsTopic,
  feedOrder: readonly NewsFeed[],
): NewsItem[] {
  const rank = (feed: NewsFeed): number => {
    const index = feedOrder.indexOf(feed);
    return index === -1 ? feedOrder.length : index;
  };
  return items
    .filter((item): item is NewsItem => item.kind === 'news')
    .filter((item) => topic === 'all' || item.category === topic)
    .sort((a, b) => timeOf(b) - timeOf(a) || rank(a.feed) - rank(b.feed));
}
