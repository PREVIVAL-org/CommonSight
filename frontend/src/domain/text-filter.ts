/**
 * Filters items by a keyword in title and description (U-30).
 */
import type { Item } from '../contract/types';

/** Searchable text per kind: title plus the descriptive fields of the kind. */
export function searchableText(item: Item): string {
  switch (item.kind) {
    case 'warning':
      return [item.title, item.area, ...item.sections.map((section) => section.text)].join(' ');
    case 'trafficNotice':
      return [item.title, item.road, item.noticeType, item.description]
        .filter((part) => part !== undefined)
        .join(' ');
    case 'earthquake':
      return [item.title, item.place].join(' ');
    case 'news':
      return [item.title, item.feed].join(' ');
    case 'measurement':
    case 'modelValue':
    case 'index':
      return item.title;
  }
}

function normalize(text: string): string {
  return text.toLocaleLowerCase('de').normalize('NFKD').replace(/\p{M}/gu, '');
}

/** All words of the search must occur; an empty search lets everything through. */
export function filterByText(items: readonly Item[], query: string): Item[] {
  const words = normalize(query)
    .split(/\s+/)
    .filter((word) => word.length > 0);
  if (words.length === 0) return [...items];
  return items.filter((item) => {
    const haystack = normalize(searchableText(item));
    return words.every((word) => haystack.includes(word));
  });
}
