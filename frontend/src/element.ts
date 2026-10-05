/**
 * Entry point: custom element <commonsight-map>; reads attributes, creates the open shadow root, starts the
 * assembly and detaches it again (Architecture 9.6).
 */
import type { Instance } from './assemble';
import { assemble, createDom } from './assemble';
import type { CountryChoice } from './domain/country-choice';
import { parseCountryChoice } from './domain/country-choice';
import type { Access, Branding, ElementConfig } from './entry-types';
import { DEFAULT_SITE_NAME } from './entry-types';
import { parseThemeMode } from './theme/theme-mode';

/** Key in localStorage without the attribute storage-key. */
export const DEFAULT_STORAGE_KEY = 'commonsight';

/** Default base URL: two levels above the bundle (`<base>/app/<version>/commonsight.js`). */
const DEFAULT_BASE_URL = new URL(/* @vite-ignore */ '../../', import.meta.url).href;
const ASSET_BASE_URL = new URL(/* @vite-ignore */ './', import.meta.url).href;

function parseCountry(value: string | null): CountryChoice | null {
  return parseCountryChoice(value?.toUpperCase());
}

function withTrailingSlash(url: string): string {
  return url.endsWith('/') ? url : `${url}/`;
}

/** An attribute that is set and not empty, as a URL relative to the page; else null. */
function urlAttribute(element: HTMLElement, name: string): string | null {
  const value = element.getAttribute(name)?.trim() ?? '';
  return value === '' ? null : new URL(value, element.ownerDocument.baseURI).href;
}

function readBranding(element: HTMLElement): Branding {
  const name = element.getAttribute('site-name')?.trim() ?? '';
  return {
    name: name === '' ? DEFAULT_SITE_NAME : name,
    logo: urlAttribute(element, 'logo') ?? `${ASSET_BASE_URL}favicon.svg`,
    logoDark: urlAttribute(element, 'logo-dark'),
    logoLink: urlAttribute(element, 'logo-link'),
    logoAlt: element.getAttribute('logo-alt')?.trim() ?? '',
  };
}

/** access="denied" comes from gate.php (A-D3); anything else admits. */
function readAccess(element: HTMLElement): Access {
  return {
    admitted: element.getAttribute('access') !== 'denied',
    community: element.getAttribute('community')?.trim() ?? '',
    loginUrl: urlAttribute(element, 'login-url'),
    registerUrl: urlAttribute(element, 'register-url'),
  };
}

function readConfig(element: HTMLElement): ElementConfig {
  const baseAttribute = element.getAttribute('base-url');
  const base =
    baseAttribute === null || baseAttribute.trim() === ''
      ? DEFAULT_BASE_URL
      : new URL(baseAttribute, element.ownerDocument.baseURI).href;
  return {
    baseUrl: withTrailingSlash(base),
    assetBaseUrl: ASSET_BASE_URL,
    country: parseCountry(element.getAttribute('country')) ?? 'DE',
    lang: element.getAttribute('lang') ?? 'de',
    theme: parseThemeMode(element.getAttribute('theme')),
    storageKey: element.getAttribute('storage-key') || DEFAULT_STORAGE_KEY,
    embedded: element.hasAttribute('embedded'),
    header: element.getAttribute('header') !== 'none',
    branding: readBranding(element),
    access: readAccess(element),
  };
}

export class CommonSightMapElement extends HTMLElement {
  static readonly observedAttributes = ['country', 'theme', 'embedded'];

  private instance: Instance | null = null;

  connectedCallback(): void {
    if (this.instance !== null) return;
    const shadow = this.shadowRoot ?? this.attachShadow({ mode: 'open' });
    this.instance = assemble(createDom(this, shadow), readConfig(this));
    this.dispatchEvent(new CustomEvent('commonsight-ready', { bubbles: true, composed: true }));
  }

  disconnectedCallback(): void {
    this.instance?.dispose();
    this.instance = null;
  }

  attributeChangedCallback(name: string, previous: string | null, value: string | null): void {
    const actions = this.instance?.bundle.actions;
    if (actions === undefined || previous === value) return;
    if (name === 'theme') actions.setThemeAttribute(parseThemeMode(value));
    else if (name === 'embedded') actions.setEmbedded(value !== null);
    else if (name === 'country') {
      const country = parseCountry(value);
      if (country !== null) actions.setCountry(country);
    }
  }
}

if (customElements.get('commonsight-map') === undefined) {
  customElements.define('commonsight-map', CommonSightMapElement);
}
