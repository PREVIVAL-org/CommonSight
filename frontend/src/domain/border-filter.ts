/**
 * Items of the border zone near the selection, for the section "Grenzgebiet" (border area) of the lists: with a
 * region the items near this region (75 km), otherwise those near one of the selected countries (150 km).
 */
import type { Country, Item } from '../contract/types';

export function nearSelection(
  items: readonly Item[],
  countries: readonly Country[],
  regionId: string | null,
): Item[] {
  return items.filter((item) => {
    const near = item.near;
    if (near === undefined) return false;
    return regionId === null
      ? near.countries.some((country) => countries.includes(country))
      : near.regionIds.includes(regionId);
  });
}
