/**
 * Encodes the combined choice of country and region as one value and reads it back (U-10 to U-12,
 * ADR 0037); pure.
 */
import type { CountryChoice } from './country-choice';
import { ALL_COUNTRIES, parseCountryChoice } from './country-choice';

export interface PlaceChoice {
  country: CountryChoice;
  regionId: string | null;
}

/** "ALL" for all countries, "AT" for all of Austria, "AT:AT-9" for Vienna. */
export function encodePlace(choice: PlaceChoice): string {
  return choice.regionId === null ? choice.country : `${choice.country}:${choice.regionId}`;
}

export function decodePlace(value: string): PlaceChoice | null {
  const [code, regionId] = value.split(':', 2);
  const country = parseCountryChoice(code);
  if (country === null) return null;
  const region = regionId === undefined || regionId === '' ? null : regionId;
  if (region !== null && (country === ALL_COUNTRIES || !region.startsWith(`${country}-`))) return null;
  return { country, regionId: region };
}
