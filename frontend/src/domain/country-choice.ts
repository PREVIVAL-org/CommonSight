/**
 * Choice of one country or all countries ("Alle", ADR 0037): which countries it covers and which country
 * supplies the country-specific details (reference place of the key figures, news order); pure.
 */
import { COUNTRIES, countries } from '../contract/master-data';
import type { Country } from '../contract/types';
import type { BoundingBox } from './item-location';

export const ALL_COUNTRIES = 'ALL';

export type CountryChoice = Country | typeof ALL_COUNTRIES;

export const COUNTRY_CHOICES: readonly CountryChoice[] = [ALL_COUNTRIES, ...COUNTRIES];

/** With "Alle", Germany supplies the reference place and the order of the news sources. */
const REFERENCE_COUNTRY: Country = 'DE';

const SINGLE: Readonly<Record<Country, readonly Country[]>> = { DE: ['DE'], AT: ['AT'], CH: ['CH'] };

/** Constant lists, so that memoized selectors stay stable. */
export function countriesOf(choice: CountryChoice): readonly Country[] {
  return choice === ALL_COUNTRIES ? COUNTRIES : SINGLE[choice];
}

export function referenceCountry(choice: CountryChoice): Country {
  return choice === ALL_COUNTRIES ? REFERENCE_COUNTRY : choice;
}

/** Bounding rectangle of the countries in the choice [west, south, east, north]. */
export function choiceBounds(choice: CountryChoice): BoundingBox {
  const boxes = countriesOf(choice).map((country) => countries[country].bounds);
  return [
    Math.min(...boxes.map((box) => box.west)),
    Math.min(...boxes.map((box) => box.south)),
    Math.max(...boxes.map((box) => box.east)),
    Math.max(...boxes.map((box) => box.north)),
  ];
}

export function parseCountryChoice(value: unknown): CountryChoice | null {
  return COUNTRY_CHOICES.find((choice) => choice === value) ?? null;
}
