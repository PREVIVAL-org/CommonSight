/**
 * Store actions only change state: country switch, layers, "Auf Karte anzeigen" (show on map), data, commands,
 * toasts.
 */
import { describe, expect, it } from 'vitest';
import { createInitialState } from '../../src/state/initial-state';
import { snapshot, statusAT } from '../support/fixtures';
import { NOW } from '../support/deps';
import { createTestStore, loadInto } from '../support/store';

describe('selection actions', () => {
  it('country change resets region, sheet and map focus (U-11)', () => {
    const { store, actions } = createTestStore('AT');
    actions.setRegion('AT-9');
    actions.openSheet({ type: 'sources' });
    actions.showOnMap('water', 'x');
    actions.setCountry('CH');
    const state = store.getState();
    expect(state.selection).toMatchObject({ country: 'CH', regionId: null });
    expect(state.ui.sheet).toBeNull();
    expect(state.map.focus).toBeNull();
  });

  it('activating space or a setup layer opens its sheet (U-21)', () => {
    const { store, actions } = createTestStore('AT');
    actions.toggleLayer('space');
    expect(store.getState().ui.sheet).toEqual({ type: 'layer', layer: 'space' });
    actions.closeSheet();
    actions.toggleLayer('space');
    expect(store.getState().ui.sheet).toBeNull();
  });

  it('show on map activates the layer, switches to the map and requests focus (K-09)', () => {
    const { store, actions } = createTestStore('AT');
    actions.setView('events');
    actions.showOnMap('nature', 'us7000abcd');
    const state = store.getState();
    expect(state.selection.view).toBe('overview');
    expect(state.selection.activeLayers).toContain('nature');
    expect(state.map.focus).toMatchObject({ type: 'item', layer: 'nature', itemId: 'us7000abcd', seq: 1 });
  });

  it('pages the main list and the section "Grenzgebiet" separately', () => {
    const { store, actions } = createTestStore('AT');
    actions.showMoreBorder();
    expect(store.getState().selection).toMatchObject({ listLimit: 60, borderLimit: 120 });
    actions.showMore();
    expect(store.getState().selection).toMatchObject({ listLimit: 120, borderLimit: 120 });
    actions.setQuery('Wien');
    expect(store.getState().selection).toMatchObject({ listLimit: 60, borderLimit: 60 });
  });

  it('open entries switches to the list view of the layer, a layer without one stays', () => {
    const { store, actions } = createTestStore('AT');
    actions.setListLayer('events', 'traffic');
    actions.openEntries('warnings');
    expect(store.getState().selection).toMatchObject({ view: 'events', listLayers: { events: 'warnings' } });
    actions.openEntries('weather');
    expect(store.getState().selection).toMatchObject({
      view: 'measurements',
      listLayers: { measurements: 'weather' },
    });
    actions.openEntries('space');
    expect(store.getState().selection.view).toBe('measurements');
  });
});

describe('data actions', () => {
  it('keeps the clock offset on an unchanged status (304)', () => {
    const bundle = createTestStore('AT');
    const status = statusAT();
    bundle.actions.statusReceived(status, NOW - 5000);
    expect(bundle.store.getState().data.statuses.AT?.clockOffsetMs).toBe(5000);
    bundle.actions.statusReceived(status, NOW + 60_000);
    expect(bundle.store.getState().data.statuses.AT?.clockOffsetMs).toBe(5000);
  });

  it('marks and clears failed snapshots (U-54)', () => {
    const bundle = createTestStore('AT');
    bundle.actions.snapshotFailed('AT/warnings');
    bundle.actions.snapshotFailed('AT/warnings');
    expect(bundle.store.getState().data.failed).toEqual(['AT/warnings']);
    loadInto(bundle, null, [snapshot('warnings-AT')]);
    expect(bundle.store.getState().data.failed).toEqual([]);
  });
});

describe('ui and map actions', () => {
  it('counts commands and keeps at most three toasts', () => {
    const { store, actions } = createTestStore('AT');
    actions.request('locate');
    actions.request('locate');
    expect(store.getState().ui.commands.locate).toBe(2);
    ['toast.located', 'toast.locateFailed', 'toast.mapError', 'toast.fullscreenUnsupported'].forEach((key) =>
      actions.pushToast(key as 'toast.located', 'info'),
    );
    expect(store.getState().ui.toasts.map((toast) => toast.id)).toEqual([2, 3, 4]);
    actions.dismissToast(3);
    expect(store.getState().ui.toasts.map((toast) => toast.id)).toEqual([2, 4]);
  });

  it('accepts region outlines only for the current region', () => {
    const { store, actions } = createTestStore('AT');
    actions.setRegion('AT-9');
    actions.outlineLoaded('AT-7', { type: 'FeatureCollection', features: [] });
    expect(store.getState().map.outline).toEqual({ state: 'idle' });
    actions.outlineFailed('AT-9');
    expect(store.getState().map.outline).toEqual({ state: 'error', regionId: 'AT-9' });
  });
});

describe('initial state', () => {
  it('prefers stored settings over the environment country (U-10) and keeps the region only for that country', () => {
    const base = {
      embedded: false,
      themeAttribute: null,
      themeChoice: null,
      environment: { online: true, visible: true, systemDark: false, nowMs: NOW },
    };
    const state = createInitialState({
      ...base,
      country: 'AT',
      settings: { country: 'CH', regionId: 'CH-ZH', activeLayers: ['air'] },
    });
    expect(state.selection).toMatchObject({ country: 'CH', regionId: 'CH-ZH', activeLayers: ['air'] });
    expect(createInitialState({ ...base, country: 'AT', settings: {} }).selection.activeLayers).toEqual([
      'warnings',
      'weather',
      'water',
    ]);
  });
});
