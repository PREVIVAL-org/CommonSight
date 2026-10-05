/**
 * Returns the regions of a country sorted alphabetically for the region selector (U-12).
 */
import type { Region } from '../contract/master-data';
import type { Country } from '../contract/types';

export function regionOptions(all: readonly Region[], country: Country, collator: Intl.Collator): Region[] {
  return all.filter((region) => region.country === country).sort((a, b) => collator.compare(a.name, b.name));
}

export function findRegion(all: readonly Region[], regionId: string | null): Region | null {
  if (regionId === null) return null;
  return all.find((region) => region.id === regionId) ?? null;
}
