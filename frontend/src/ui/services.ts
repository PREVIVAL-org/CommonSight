/**
 * React contexts as a thin bridge to store, actions, selectors and texts, and to the nodes in the shadow root
 * (Architecture 9.1, 9.5).
 */
import { createContext } from 'react';
import type { Access, Branding } from '../entry-types';
import { DEFAULT_SITE_NAME } from '../entry-types';
import type { Translator } from '../i18n/translator';
import type { Selectors } from '../state/selectors';
import type { AppActions, AppStore } from '../state/store';

export interface UiServices {
  store: AppStore;
  actions: AppActions;
  selectors: Selectors;
  t: Translator;
}

export const ServicesContext = createContext<UiServices | null>(null);

/** Node inside the shadow root that all Radix portals render into. */
export const PortalContainerContext = createContext<HTMLElement | null>(null);

/** Map container created outside React, which the map area attaches. */
export const MapContainerContext = createContext<HTMLElement | null>(null);

/** What the host page set for header, logo and access (B-D1, B-D2, A-D2); fixed for the life of the element. */
export interface PageSettings {
  header: boolean;
  branding: Branding;
  access: Access;
}

/** Without a host page (tests): header with the icon of the bundle, admitted. */
export const DEFAULT_PAGE: PageSettings = {
  header: true,
  branding: { name: DEFAULT_SITE_NAME, logo: 'favicon.svg', logoDark: null, logoLink: null, logoAlt: '' },
  access: { admitted: true, community: '', loginUrl: null, registerUrl: null },
};

export const PageContext = createContext<PageSettings>(DEFAULT_PAGE);
