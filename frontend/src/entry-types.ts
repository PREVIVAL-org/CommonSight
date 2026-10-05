/**
 * Describes the configuration, nodes and core of an element that the entry point passes to the factories.
 */
import type { CountryChoice } from './domain/country-choice';
import type { Translator } from './i18n/translator';
import type { Selectors } from './state/selectors';
import type { AppStoreBundle } from './state/store';
import type { ThemeMode } from './theme/theme-mode';

/** Name in the header when the installation sets none (attribute site-name). */
export const DEFAULT_SITE_NAME = 'CommonSight';

/** Name and logo in the header (B-D2): from the host page, else CommonSight and its icon of the bundle. */
export interface Branding {
  /** Name of the installation in the header (attribute site-name), else DEFAULT_SITE_NAME */
  name: string;
  logo: string;
  /** Logo for the dark theme; null: the same as in light */
  logoDark: string | null;
  /** Where the logo leads; null: no link */
  logoLink: string | null;
  logoAlt: string;
}

/** Access to the start page (A-D2, A-D3): set by gate.php when a visitor is not admitted. */
export interface Access {
  admitted: boolean;
  /** Name of the community that admits, e.g. "PREVIVAL.org" */
  community: string;
  loginUrl: string | null;
  registerUrl: string | null;
}

export interface ElementConfig {
  /** Root for api/, data/, tiles/, map/ (with trailing slash). */
  baseUrl: string;
  /** Directory of the bundle for bundled files (logo). */
  assetBaseUrl: string;
  /** Initial selection: a country or `ALL` (ADR 0037). */
  country: CountryChoice;
  lang: string;
  theme: ThemeMode | null;
  storageKey: string;
  embedded: boolean;
  /** Own header with logo, name, clock and light/dark switch; off with header="none" (B-D1, T-10). */
  header: boolean;
  branding: Branding;
  access: Access;
}

export interface ElementDom {
  host: HTMLElement;
  shadow: ShadowRoot;
  root: HTMLElement;
  app: HTMLElement;
  portal: HTMLElement;
  mapContainer: HTMLElement;
}

export interface Core {
  bundle: AppStoreBundle;
  selectors: Selectors;
  t: Translator;
}
