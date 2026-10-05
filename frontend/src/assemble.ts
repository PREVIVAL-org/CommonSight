/**
 * Entry factory: assembles I/O, store, selectors, flow control, map and UI for one element
 * (Architecture 1.3.3).
 */
import { startMap } from './assemble-map';
import { resolveBasemap } from './application/basemap-resolver';
import { startChangeNotifier } from './application/change-notifier';
import type { ChangeDetail } from './application/change-notifier';
import { startClockTicker } from './application/clock-ticker';
import { DataSync } from './application/data-sync';
import { startDeviceCommands } from './application/device-commands';
import { startEnvironmentSync } from './application/environment-sync';
import { startNewEntriesNotifier } from './application/new-entries-notifier';
import { RefreshScheduler } from './application/refresh-scheduler';
import { startRegionOutlineLoader } from './application/region-outline-loader';
import { startRootAttributeSync } from './application/root-attribute-sync';
import { loadStoredSettings, startSettingsSync } from './application/settings-sync';
import { startSheetHistory } from './application/sheet-history';
import { startToastExpiry } from './application/toast-expiry';
import type { Core, ElementConfig, ElementDom } from './entry-types';
import { catalogsFor } from './i18n/catalogs';
import { createFormats } from './i18n/formats';
import { createTranslator } from './i18n/translator';
import { readBrowserLocale } from './infrastructure/browser-locale';
import { createFullscreen } from './infrastructure/fullscreen';
import { createLocator } from './infrastructure/geolocation';
import { HttpJsonFileApi } from './infrastructure/json-file-api';
import { createPageEnvironment } from './infrastructure/page-visibility';
import { createRootAttributes } from './infrastructure/root-attributes';
import { createBrowserHistory } from './infrastructure/browser-history';
import { browserStorage, createSettingsStorage } from './infrastructure/settings-storage';
import { HttpSnapshotApi } from './infrastructure/snapshot-api';
import { HttpStatusApi } from './infrastructure/status-api';
import { createSystemTheme } from './infrastructure/system-theme';
import { createViewport } from './infrastructure/viewport';
import { systemClock, systemTimer } from './infrastructure/timer';
import { createInitialState } from './state/initial-state';
import { createSelectors } from './state/selectors';
import type { AppStoreBundle } from './state/store';
import { createAppStore } from './state/store';
import { ELEMENT_STYLESHEETS } from './theme/stylesheets';
import { mountUi } from './ui/mount';
import { adoptStyles } from './infrastructure/adopted-styles';

export interface Instance {
  bundle: AppStoreBundle;
  dispose(): void;
}

export function createDom(host: HTMLElement, shadow: ShadowRoot): ElementDom {
  adoptStyles(shadow, 'element', ELEMENT_STYLESHEETS, 'last');
  const doc = host.ownerDocument;
  const root = doc.createElement('div');
  root.className = 'root';
  const app = doc.createElement('div');
  const portal = doc.createElement('div');
  portal.className = 'portal';
  const mapContainer = doc.createElement('div');
  root.append(app, portal);
  shadow.replaceChildren(root);
  return { host, shadow, root, app, portal, mapContainer };
}

// eslint-disable-next-line max-lines-per-function -- Assembly: each line wires exactly one building block (Architecture 1.3.3, 1.3.6).
function createCore(
  config: ElementConfig,
  win: Window,
): Core & { settings: ReturnType<typeof createSettingsStorage> } {
  const locale = readBrowserLocale(win.navigator);
  const catalogs = catalogsFor(config.lang);
  const formats = createFormats({ ...locale, textLang: catalogs.lang });
  const t = createTranslator({
    ...catalogs,
    // Plural forms follow the language of the texts (German "1 Eintrag", "21 Einträge"), not the browser's.
    locale: catalogs.lang,
    formatNumber: (value) => formats.number(value),
  });
  const settings = createSettingsStorage(browserStorage(win), config.storageKey);
  const stored = loadStoredSettings(settings);
  const page = createPageEnvironment(win);
  const bundle = createAppStore(
    createInitialState({
      country: config.country,
      embedded: config.embedded,
      themeAttribute: config.theme,
      themeChoice: stored.themeChoice,
      settings: stored.settings,
      environment: {
        online: page.isOnline(),
        visible: page.isVisible(),
        systemDark: createSystemTheme(win).prefersDark(),
        nowMs: systemClock.now(),
      },
    }),
  );
  const selectors = createSelectors({ t, format: formats }, new Intl.Collator(locale.locales[0] ?? 'de'));
  return { bundle, selectors, t, settings };
}

// eslint-disable-next-line max-lines-per-function -- Assembly: each line starts exactly one flow (Architecture 1.3.3, 1.3.6).
function startApplication(
  core: ReturnType<typeof createCore>,
  dom: ElementDom,
  config: ElementConfig,
): (() => void)[] {
  const win = dom.host.ownerDocument.defaultView ?? window;
  const { store, actions } = core.bundle;
  const fetchFn = win.fetch.bind(win);
  const files = new HttpJsonFileApi(config.baseUrl, fetchFn);
  const sources = {
    status: new HttpStatusApi(config.baseUrl, fetchFn),
    snapshots: new HttpSnapshotApi(config.baseUrl, fetchFn),
  };
  const sync = new DataSync(sources, store, actions, systemClock);
  const emit = (detail: ChangeDetail): void => {
    dom.host.dispatchEvent(new CustomEvent('commonsight-change', { detail, bubbles: true, composed: true }));
  };
  void resolveBasemap(files, actions);
  return [
    startRootAttributeSync(store, createRootAttributes(dom.root, dom.host)),
    startEnvironmentSync(createPageEnvironment(win), createSystemTheme(win), actions),
    startSettingsSync(core.settings, store),
    startClockTicker(systemTimer, systemClock, actions),
    startToastExpiry(store, systemTimer, actions),
    startNewEntriesNotifier(store, actions, createViewport(win)),
    new RefreshScheduler(sync, store, systemTimer, systemClock).start(),
    startRegionOutlineLoader(files, store, actions),
    startDeviceCommands({
      store,
      actions,
      locator: createLocator(win.navigator.geolocation),
      fullscreen: createFullscreen(dom.host),
    }),
    startChangeNotifier(store, emit),
    startSheetHistory(store, actions, createBrowserHistory(win, dom.host)),
  ];
}

/** A visitor the start page does not admit (A-D2): header and members card only, no map, no data. */
function startPage(core: ReturnType<typeof createCore>, dom: ElementDom): (() => void)[] {
  const win = dom.host.ownerDocument.defaultView ?? window;
  const { store, actions } = core.bundle;
  return [
    startRootAttributeSync(store, createRootAttributes(dom.root, dom.host)),
    startEnvironmentSync(createPageEnvironment(win), createSystemTheme(win), actions),
    startSettingsSync(core.settings, store),
    startClockTicker(systemTimer, systemClock, actions),
  ];
}

export function assemble(dom: ElementDom, config: ElementConfig): Instance {
  const win = dom.host.ownerDocument.defaultView ?? window;
  const core = createCore(config, win);
  const admitted = config.access.admitted;
  // The map library is loaded immediately and in parallel with the UI (Architecture 9.7).
  const stopMap = admitted ? startMap(core, dom, config) : () => undefined;
  const stops = admitted ? startApplication(core, dom, config) : startPage(core, dom);
  const unmount = mountUi(
    { root: dom.app, portal: dom.portal, mapContainer: dom.mapContainer },
    { store: core.bundle.store, actions: core.bundle.actions, selectors: core.selectors, t: core.t },
    { header: config.header, branding: config.branding, access: config.access },
  );
  return {
    bundle: core.bundle,
    dispose: () => {
      unmount();
      stopMap();
      stops.forEach((stop) => stop());
    },
  };
}
