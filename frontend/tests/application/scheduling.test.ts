/**
 * Interval, visibility, country switch, saving settings, clock, toasts, device commands, outline and basemap,
 * with test doubles.
 */
import { describe, expect, it, vi } from 'vitest';
import { resolveBasemap } from '../../src/application/basemap-resolver';
import { startChangeNotifier } from '../../src/application/change-notifier';
import {
  CLOCK_INTERVAL_MS,
  EVALUATION_INTERVAL_MS,
  startClockTicker,
} from '../../src/application/clock-ticker';
import type { Synchronizer } from '../../src/application/data-sync';
import { startDeviceCommands } from '../../src/application/device-commands';
import { RefreshScheduler, STATUS_INTERVAL_MS } from '../../src/application/refresh-scheduler';
import { startRegionOutlineLoader } from '../../src/application/region-outline-loader';
import { startRootAttributeSync } from '../../src/application/root-attribute-sync';
import { loadStoredSettings, startSettingsSync } from '../../src/application/settings-sync';
import { startToastExpiry } from '../../src/application/toast-expiry';
import { LocationError } from '../../src/infrastructure/geolocation';
import type { JsonFileApi } from '../../src/infrastructure/json-file-api';
import { statusAT } from '../support/fixtures';
import { FixedClock, ManualTimer, MemorySettingsStorage, settle } from '../support/doubles';
import { NOW } from '../support/deps';
import { createTestStore } from '../support/store';

function fakeSync(): Synchronizer & { status: number; snapshots: number; aborts: number } {
  const sync = {
    status: 0,
    snapshots: 0,
    aborts: 0,
    syncStatus: async () => {
      sync.status += 1;
    },
    syncSnapshots: async () => {
      sync.snapshots += 1;
    },
    abortAll: () => {
      sync.aborts += 1;
    },
  };
  return sync;
}

function files(data: Record<string, unknown>): JsonFileApi & { requested: string[] } {
  const requested: string[] = [];
  return {
    requested,
    urlOf: (path) => `https://example.org/commonsight/${path}`,
    fetchJson: async (path) => {
      requested.push(path);
      if (!(path in data)) throw new Error('404');
      return data[path];
    },
  };
}

describe('RefreshScheduler', () => {
  it('syncs at start and 60 s after each fetch, only while visible and online (U-51)', () => {
    const { store, actions } = createTestStore();
    const sync = fakeSync();
    const timer = new ManualTimer();
    const stop = new RefreshScheduler(sync, store, timer, new FixedClock(NOW)).start();
    expect(sync.status).toBe(1);
    timer.flushTimeouts();
    expect(sync.status).toBe(2);
    actions.setVisible(false);
    timer.flushTimeouts();
    expect(sync.status).toBe(2);
    stop();
    expect(sync.aborts).toBe(1);
    timer.flushTimeouts();
    expect(sync.status).toBe(2);
  });

  it('syncs immediately when the tab returns with an old state or the connection returns', () => {
    const { store, actions } = createTestStore();
    const sync = fakeSync();
    const clock = new FixedClock(NOW);
    new RefreshScheduler(sync, store, new ManualTimer(), clock).start();
    actions.statusReceived(statusAT(), NOW);
    actions.setVisible(false);
    actions.setVisible(true);
    expect(sync.status).toBe(1);
    actions.setVisible(false);
    clock.current = NOW + STATUS_INTERVAL_MS;
    actions.setVisible(true);
    expect(sync.status).toBe(2);
    actions.setOnline(false);
    actions.setOnline(true);
    expect(sync.status).toBe(3);
  });

  it('aborts and restarts on country change, loads snapshots on changed needs', () => {
    const { actions, store } = createTestStore();
    const sync = fakeSync();
    const timer = new ManualTimer();
    new RefreshScheduler(sync, store, timer, new FixedClock(NOW)).start();
    actions.setCountry('DE');
    expect(sync.aborts).toBe(1);
    expect(sync.status).toBe(2);
    // The country switch replaces the planned fetch instead of adding a second one.
    timer.flushTimeouts();
    expect(sync.status).toBe(3);
    actions.request('locate');
    expect(sync.status).toBe(3);
    actions.toggleLayer('nature');
    expect(sync.snapshots).toBe(1);
    actions.setQuery('x');
    expect(sync.snapshots).toBe(1);
  });
});

describe('settings sync', () => {
  it('loads valid settings and theme', () => {
    const storage = new MemorySettingsStorage();
    storage.settings = { country: 'DE', activeLayers: ['water'] };
    storage.theme = 'dark';
    expect(loadStoredSettings(storage)).toEqual({
      settings: { country: 'DE', activeLayers: ['water'] },
      themeChoice: 'dark',
    });
  });

  it('writes only when a persisted field changes (U-60)', () => {
    const storage = new MemorySettingsStorage();
    const { store, actions } = createTestStore();
    startSettingsSync(storage, store);
    actions.tickClock(NOW + 1000);
    expect(storage.writes).toBe(0);
    actions.setRegion('AT-9');
    expect(storage.settings).toEqual({
      country: 'AT',
      regionId: 'AT-9',
      activeLayers: ['warnings', 'weather', 'water'],
      mapNoticeHidden: false,
      borderZone: true,
    });
    // The credits behind the "i" are not saved: opening them writes nothing.
    const writes = storage.writes;
    actions.setAttributionHidden(false);
    expect(storage.writes).toBe(writes);
    actions.setMapView({ lon: 16.37, lat: 48.21, zoom: 9 });
    expect(storage.settings).toMatchObject({
      mapView: { lon: 16.37, lat: 48.21, zoom: 9, place: 'AT|AT-9' },
    });
    actions.chooseTheme('dark');
    expect(storage.theme).toBe('dark');
  });
});

describe('clock ticker and toast expiry', () => {
  it('ticks the clock every second and re-evaluates every 30 s (U-53)', () => {
    const { store, actions } = createTestStore();
    const timer = new ManualTimer();
    const clock = new FixedClock(NOW + 5000);
    startClockTicker(timer, clock, actions);
    timer.fire(CLOCK_INTERVAL_MS);
    expect(store.getState().env.clockMs).toBe(NOW + 5000);
    expect(store.getState().env.evaluationMs).toBe(NOW);
    timer.fire(EVALUATION_INTERVAL_MS);
    expect(store.getState().env.evaluationMs).toBe(NOW + 5000);
  });

  it('dismisses toasts after their time (U-05)', () => {
    const { store, actions } = createTestStore();
    const timer = new ManualTimer();
    startToastExpiry(store, timer, actions);
    actions.pushToast('toast.located', 'info');
    timer.flushTimeouts();
    expect(store.getState().ui.toasts).toEqual([]);
  });

  it('gives a new toast a new id, so the timer of a dismissed one cannot close it', () => {
    const { store, actions } = createTestStore();
    actions.pushToast('toast.located', 'info');
    const [first] = store.getState().ui.toasts;
    actions.dismissToast(first?.id ?? 0);
    actions.pushToast('toast.located', 'info');
    expect(store.getState().ui.toasts.map((toast) => toast.id)).not.toContain(first?.id);
  });
});

describe('device commands', () => {
  it('centres the map on the location or reports why it failed (K-02)', async () => {
    const bundle = createTestStore();
    const locate = vi
      .fn()
      .mockResolvedValueOnce({ lon: 16.37, lat: 48.2 })
      .mockRejectedValueOnce(new LocationError('unsupported'))
      .mockRejectedValueOnce(new LocationError('failed'));
    const fullscreen = {
      toggle: vi.fn().mockResolvedValueOnce('unsupported').mockRejectedValueOnce(new Error('denied')),
    };
    startDeviceCommands({ ...bundle, locator: { locate }, fullscreen });
    bundle.actions.request('locate');
    await settle();
    expect(bundle.store.getState().map.focus).toMatchObject({ type: 'point', lon: 16.37, lat: 48.2 });
    bundle.actions.request('locate');
    await settle();
    bundle.actions.request('locate');
    await settle();
    bundle.actions.request('fullscreen');
    await settle();
    expect(bundle.store.getState().ui.toasts.map((toast) => ('key' in toast ? toast.key : null))).toEqual([
      'toast.locateUnsupported',
      'toast.locateFailed',
      'toast.fullscreenUnsupported',
    ]);
  });
});

describe('region outline and basemap', () => {
  it('loads the outline of the chosen region only and allows a retry (U-17)', async () => {
    const { store, actions } = createTestStore();
    const api = files({ 'map/regions/AT-9.geojson': { type: 'FeatureCollection', features: [] } });
    startRegionOutlineLoader(api, store, actions);
    expect(api.requested).toEqual([]);
    actions.setRegion('AT-9');
    await settle();
    expect(store.getState().map.outline).toMatchObject({ state: 'ready', regionId: 'AT-9' });
    actions.setRegion('AT-7');
    await settle();
    expect(store.getState().map.outline).toEqual({ state: 'error', regionId: 'AT-7' });
    actions.request('retryOutline');
    await settle();
    expect(api.requested).toEqual([
      'map/regions/AT-9.geojson',
      'map/regions/AT-7.geojson',
      'map/regions/AT-7.geojson',
    ]);
    actions.setRegion(null);
    expect(store.getState().map.outline).toEqual({ state: 'idle' });
  });

  it('resolves the archive from the manifest and reports a missing basemap (K-12)', async () => {
    const ok = createTestStore();
    await resolveBasemap(
      files({ 'tiles/manifest.json': { file: 'dach-2026-10.pmtiles', maxzoom: 14 } }),
      ok.actions,
    );
    expect(ok.store.getState().map.tilesUrl).toBe(
      'https://example.org/commonsight/tiles/dach-2026-10.pmtiles',
    );
    const missing = createTestStore();
    await resolveBasemap(files({}), missing.actions);
    expect(missing.store.getState().map.tilesUrl).toBeNull();
    const invalid = createTestStore();
    await resolveBasemap(files({ 'tiles/manifest.json': { file: '../etc/passwd' } }), invalid.actions);
    expect(invalid.store.getState().map.tilesUrl).toBeNull();
  });
});

describe('outward events and root attributes', () => {
  it('emits commonsight-change on country, region and layer changes only', () => {
    const { store, actions } = createTestStore();
    const emit = vi.fn();
    startChangeNotifier(store, emit);
    actions.setQuery('x');
    actions.setRegion('AT-9');
    actions.toggleLayer('air');
    expect(emit).toHaveBeenCalledTimes(2);
    expect(emit).toHaveBeenLastCalledWith({
      country: 'AT',
      regionId: 'AT-9',
      layers: ['warnings', 'weather', 'water', 'air'],
    });
  });

  it('applies theme and embedded mode to the root', () => {
    const { store, actions } = createTestStore();
    const apply = vi.fn();
    startRootAttributeSync(store, { apply });
    actions.setThemeAttribute('dark');
    actions.setEmbedded(true);
    expect(apply).toHaveBeenLastCalledWith({ theme: 'dark', embedded: true });
  });
});
