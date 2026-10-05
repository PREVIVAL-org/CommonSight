/**
 * Derives the page header details: theme, place and the combined country/region selector with "Alle" (U-03,
 * U-12, T-05, ADR 0037).
 */
import { COUNTRIES, countries, regions } from '../../contract/master-data';
import type { Country } from '../../contract/types';
import type { CountryChoice } from '../../domain/country-choice';
import { ALL_COUNTRIES, countriesOf } from '../../domain/country-choice';
import { encodePlace } from '../../domain/place-choice';
import { findRegion, regionOptions } from '../../domain/region-options';
import type { ThemeMode } from '../../theme/theme-mode';
import { resolveThemeMode } from '../../theme/theme-mode';
import type { AppState } from '../app-state';
import { memoizeLast } from '../memo';

/** Until the first status arrives, and from servers of older releases. */
export const DEFAULT_VICINITY_KM = 200;
import type { TextServices } from './item-selectors';

/**
 * An entry of the combined country/region selector, in list order: "Alle", then per country "Ganz {Land}" (all of
 * {country}) with its flag and below it its regions, indented.
 */
export interface PlaceOption {
  value: string;
  name: string;
  /** Country of the entry for the flag; `null` for "Alle". */
  country: Country | null;
  /** A state or canton (indented below its country). */
  region: boolean;
}

/** What the closed selector shows: the flag of the country (none with "Alle") and the name. */
export interface PlaceDisplay {
  country: Country | null;
  label: string;
}

export interface HeaderSelectors {
  themeMode(state: AppState): ThemeMode;
  /** Country or "country · region", with "Alle" the countries combined (map label). */
  placeName(state: AppState): string;
  /** Only the country, or with "Alle" the countries combined (subtitle of the detail sheet). */
  countryName(state: AppState): string;
  /** Name of the selected region or `null`. */
  regionName(state: AppState): string | null;
  /** The countries of the selection in fixed order. */
  selectedCountries(state: AppState): readonly Country[];
  /** Radius ("Umkreis") of the vicinity in km, as the installation sets it (config.php vicinityKm, ADR 0038). */
  vicinityKm(state: AppState): number;
  /** "Alle" and all countries with their regions for the combined selector (U-10 to U-12). */
  placeOptions(state: AppState): PlaceOption[];
  /** Flag and name of the current selection for the closed selector. */
  placeDisplay(state: AppState): PlaceDisplay;
  /** Value of the current selection in the format of encodePlace. */
  placeValue(state: AppState): string;
}

function buildPlaceOptions(texts: TextServices, collator: Intl.Collator): PlaceOption[] {
  const all: PlaceOption = {
    value: encodePlace({ country: ALL_COUNTRIES, regionId: null }),
    name: texts.t.ui('place.all'),
    country: null,
    region: false,
  };
  return [
    all,
    ...COUNTRIES.flatMap((country): PlaceOption[] => [
      {
        value: encodePlace({ country, regionId: null }),
        name: texts.t.ui('region.whole', { country: countries[country].name }),
        country,
        region: false,
      },
      ...regionOptions(regions, country, collator).map((region) => ({
        value: encodePlace({ country, regionId: region.id }),
        name: region.name,
        country,
        region: true,
      })),
    ]),
  ];
}

// eslint-disable-next-line max-lines-per-function -- Only collects selectors; each property is a separate, individually tested selector (Architecture 1.3.6).
export function createHeaderSelectors(texts: TextServices, collator: Intl.Collator): HeaderSelectors {
  const options = buildPlaceOptions(texts, collator);
  const display = memoizeLast((country: CountryChoice, regionId: string | null): PlaceDisplay => {
    if (country === ALL_COUNTRIES) return { country: null, label: texts.t.ui('place.all') };
    const region = findRegion(regions, regionId);
    return {
      country,
      label: region?.name ?? texts.t.ui('region.whole', { country: countries[country].name }),
    };
  });
  const allName = texts.t.ui('place.allName');
  const countryName = (state: AppState): string =>
    state.selection.country === ALL_COUNTRIES ? allName : countries[state.selection.country].name;
  return {
    themeMode: (state) =>
      resolveThemeMode({
        attribute: state.env.themeAttribute,
        choice: state.env.themeChoice,
        systemDark: state.env.systemDark,
      }),
    placeName: (state) => {
      const country = countryName(state);
      const region = findRegion(regions, state.selection.regionId);
      return region === null ? country : texts.t.ui('app.place', { country, region: region.name });
    },
    countryName,
    regionName: (state) => findRegion(regions, state.selection.regionId)?.name ?? null,
    selectedCountries: (state) => countriesOf(state.selection.country),
    vicinityKm: (state) =>
      Object.values(state.data.statuses).find((status) => status?.response.vicinityKm !== undefined)?.response
        .vicinityKm ?? DEFAULT_VICINITY_KM,
    placeOptions: () => options,
    placeDisplay: (state) => display(state.selection.country, state.selection.regionId),
    placeValue: (state) =>
      encodePlace({ country: state.selection.country, regionId: state.selection.regionId }),
  };
}
