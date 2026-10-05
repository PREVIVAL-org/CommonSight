/**
 * Filters items by the chosen region via the regionIds determined by the fetcher (U-13 to U-16, V2).
 */
import type { Item } from '../contract/types';

export interface RegionScoped {
  /** Items that are shown and counted. */
  matched: Item[];
  /** Items without a reliable location assignment; only with a chosen region, never counted as hits (U-15). */
  unassigned: Item[];
  /** true if filtering by region actually took place. */
  filtered: boolean;
}

/**
 * @param regionFilter false for supra-regional layers (news, space): then no filtering ever takes place.
 */
export function scopeToRegion(
  items: readonly Item[],
  regionId: string | null,
  regionFilter: boolean,
): RegionScoped {
  if (regionId === null || !regionFilter) return { matched: [...items], unassigned: [], filtered: false };
  const matched: Item[] = [];
  const unassigned: Item[] = [];
  for (const item of items) {
    if (item.regionIds.includes(regionId)) matched.push(item);
    else if (item.regionMatch === 'none') unassigned.push(item);
  }
  return { matched, unassigned, filtered: true };
}
