/**
 * Selectors without React: derived values, memoization (stable references) and region filter (Architecture 9.2).
 */
import { describe, expect, it } from 'vitest';
import { LAYER_IDS, layerMeta } from '../../src/contract/master-data';
import { LAYER_LIST_IDS } from '../../src/domain/layer-slots';
import { layerUiParts } from '../../src/generated/layer-ui';
import { createSelectors } from '../../src/state/selectors';
import { snapshot, snapshotFor, statusAT, statusFor } from '../support/fixtures';
import { formats, t } from '../support/deps';
import { createTestStore, loadInto } from '../support/store';

function setup() {
  const bundle = createTestStore('AT');
  loadInto(bundle, statusAT(), [
    snapshot('warnings-AT'),
    snapshot('weather-AT'),
    snapshot('air-AT'),
    snapshot('radiation-AT'),
    snapshot('space-global'),
    snapshot('news-global'),
    snapshot('nature-AT'),
    snapshot('traffic-AT'),
  ]);
  const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));
  return { ...bundle, selectors };
}

function setupAll() {
  const bundle = createTestStore('ALL');
  loadInto(bundle, statusFor('DE'), [snapshotFor('warnings-AT', 'DE'), snapshotFor('weather-AT', 'DE')]);
  loadInto(bundle, statusAT(), [snapshot('warnings-AT'), snapshot('weather-AT'), snapshot('news-global')]);
  loadInto(bundle, statusFor('CH'), [snapshotFor('warnings-AT', 'CH')]);
  const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));
  return { ...bundle, selectors };
}

describe('selectors for "Alle" (ADR 0037)', () => {
  it('merge the entries of all countries and name the place', () => {
    const { store, selectors } = setupAll();
    const state = store.getState();
    expect(selectors.list(state, 'events').views).toHaveLength(9);
    expect(selectors.list(state, 'events')).toBe(selectors.list(state, 'events'));
    expect(selectors.panel(state)?.views).toHaveLength(3);
    expect(selectors.placeName(state)).toBe('Deutschland, Österreich und Schweiz');
    expect(selectors.placeValue(state)).toBe('ALL');
    expect(selectors.placeOptions(state)[0]).toEqual({
      value: 'ALL',
      name: 'Alle Länder',
      country: null,
      region: false,
    });
    expect(selectors.placeDisplay(state)).toEqual({ country: null, label: 'Alle Länder' });
    expect(selectors.placeDisplay(state)).toBe(selectors.placeDisplay(state));
  });

  it('take the German reference place for the metric tiles', () => {
    const { store, selectors } = setupAll();
    expect(selectors.metricTiles(store.getState())[0]?.value).toBe('18,5 °C');
  });

  it('show a layer as partial when one country is missing (U-54)', () => {
    const { store, actions, selectors } = setupAll();
    actions.snapshotFailed('CH/warnings');
    const row = selectors.layerRows(store.getState()).find((entry) => entry.layer === 'warnings');
    expect(row?.availability).toBe('partial');
  });
});

describe('selectors', () => {
  it('return the same reference for an unchanged state', () => {
    const { store, selectors } = setup();
    const state = store.getState();
    expect(selectors.list(state, 'events')).toBe(selectors.list(state, 'events'));
    expect(selectors.layerRows(state)).toBe(selectors.layerRows(state));
    expect(selectors.metricTiles(state)).toBe(selectors.metricTiles(state));
    expect(selectors.panel(state)).toBe(selectors.panel(state));
    expect(selectors.allStatusViews(state)).toBe(selectors.allStatusViews(state));
    expect(selectors.unassigned(state)).toBe(selectors.unassigned(state));
    expect(selectors.layerSheet(state, 'warnings')).toBe(selectors.layerSheet(state, 'warnings'));
  });

  it('keep references stable across unrelated changes (clock tick)', () => {
    const { store, actions, selectors } = setup();
    const before = selectors.list(store.getState(), 'events');
    actions.tickClock(Date.now());
    expect(selectors.list(store.getState(), 'events')).toBe(before);
  });

  it('list the warnings of the chosen region and count the unassigned ones', () => {
    const { store, actions, selectors } = setup();
    actions.setRegion('AT-9');
    const list = selectors.list(store.getState(), 'events');
    expect(list.layer).toBe('warnings');
    expect(list.views.map((view) => view.base.title)).toEqual(['Gewitterwarnung · Orange']);
    expect(list.unassigned).toBe(1);
    expect(list.status.regionNote).toContain('Region Wien');
    expect(selectors.placeName(store.getState())).toBe('Österreich · Wien');
    expect(selectors.unassigned(store.getState()).total).toBe(1);
  });

  it('filter by text and name the empty reason', () => {
    const { store, actions, selectors } = setup();
    actions.setQuery('gibt es nicht');
    expect(selectors.list(store.getState(), 'events').empty).toBe('noMatch');
    actions.setListLayer('measurements', 'water');
    expect(selectors.list(store.getState(), 'measurements').empty).toBe('pending');
  });

  it('page the list in steps of 60 (U-34)', () => {
    const { store, actions, selectors } = setup();
    const many = snapshot('radiation-AT');
    many.items = Array.from({ length: 130 }, (_, index) => ({ ...many.items[0]!, id: `s${index}` }));
    loadInto({ store, actions }, null, [many]);
    actions.setListLayer('measurements', 'radiation');
    let list = selectors.list(store.getState(), 'measurements');
    expect(list.views).toHaveLength(60);
    expect(list.remaining).toBe(70);
    expect(list.rows).toHaveLength(60);
    actions.showMore();
    list = selectors.list(store.getState(), 'measurements');
    expect(list.views).toHaveLength(120);
    expect(list.remaining).toBe(10);
  });

  it('say "loading", not "none", while the status is there and the snapshot is not', () => {
    const bundle = createTestStore('AT');
    loadInto(bundle, statusAT(), [snapshot('weather-AT')]);
    const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));
    const state = bundle.store.getState();
    expect(selectors.mapNotice(state)?.text).toBe('Warnungen werden abgerufen ...');
    expect(selectors.statusView(state, 'warnings').availability).toBe('loading');
    // The layer list keeps what the status says.
    expect(selectors.layerRows(state).find((row) => row.layer === 'warnings')?.availability).not.toBe(
      'loading',
    );
    bundle.actions.snapshotFailed('AT/warnings');
    expect(selectors.statusView(bundle.store.getState(), 'warnings').availability).not.toBe('loading');
  });

  it('take the vicinity of the installation from the status, 200 km before it arrives', () => {
    const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));
    const empty = createTestStore('AT');
    expect(selectors.vicinityKm(empty.store.getState())).toBe(200);
    const bundle = createTestStore('AT');
    loadInto(bundle, { ...statusAT(), vicinityKm: 150 }, []);
    expect(selectors.vicinityKm(bundle.store.getState())).toBe(150);
  });

  it('summarize layers, sources and the map notice', () => {
    const { store, selectors } = setup();
    const state = store.getState();
    const rows = selectors.layerRows(state);
    expect(rows.map((row) => row.layer)).toEqual(LAYER_LIST_IDS);
    expect(rows.find((row) => row.layer === 'water')).toMatchObject({
      active: true,
      availability: 'pending',
    });
    // Connected are the eight layers of the status fixture with data; every layer of the registry counts.
    expect(selectors.sourceSummary(state)).toEqual({ connected: 8, unreachable: 0, total: LAYER_IDS.length });
    expect(selectors.mapNotice(state)?.text).toBe('3 Warnungen in den verbundenen Quellen · unvollständig');
  });

  it('build legend, news and metric tiles', () => {
    const { store, actions, selectors } = setup();
    actions.toggleLayer('radiation');
    const state = store.getState();
    const legend = selectors.legend(state);
    const active = state.selection.activeLayers;
    expect(legend.activeCount).toBe(active.filter((id) => layerMeta(id).onMap).length);
    // The active layers that explain their point colors, with the lines their package computes from the data.
    const explained = LAYER_IDS.filter((id) => active.includes(id) && layerUiParts[id]?.legend !== undefined);
    expect(legend.entries.map((entry) => entry.layer)).toEqual(explained);
    expect(legend.entries.every((entry) => entry.lines.length > 0)).toBe(true);
    expect(explained.length).toBeGreaterThan(0);
    const withoutLegend = LAYER_IDS.find((id) => layerUiParts[id]?.legend === undefined);
    expect(withoutLegend === undefined ? null : selectors.legendEntry(state, withoutLegend)).toBeNull();
    expect(selectors.panel(state)?.views.map((view) => view.feed)).toEqual([
      'ORF.at',
      'tagesschau.de',
      'ORF.at',
    ]);
    expect(selectors.panel(state)).toMatchObject({ layer: 'news', fallbackUrl: 'https://orf.at/' });
    expect(selectors.metricTiles(state).map((tile) => tile.value)).toEqual([
      '18,5 °C',
      '47 EU-AQI',
      'Kp 5,33',
      '0,099 µSv/h',
    ]);
  });

  it('prepare the sheets', () => {
    const { store, selectors } = setup();
    const state = store.getState();
    expect(selectors.layerSheet(state, 'warnings').links?.map((link) => link.label)).toEqual([
      'AT-Alert',
      'HORA · Naturgefahren',
      'Lawinenwarndienste',
    ]);
    expect(selectors.layerSheet(state, 'weather').links).toBeNull();
    expect(selectors.itemSheet(state, 'nature', 'us7000abcd').view?.kind).toBe('earthquake');
    expect(selectors.itemSheet(state, 'nature', 'fehlt').view).toBeNull();
    expect(selectors.allStatusViews(state)).toHaveLength(LAYER_IDS.length);
  });

  it('resolve the theme from attribute, choice and system (T-05)', () => {
    const { store, actions, selectors } = setup();
    expect(selectors.themeMode(store.getState())).toBe('light');
    actions.setSystemDark(true);
    expect(selectors.themeMode(store.getState())).toBe('dark');
    actions.chooseTheme('light');
    expect(selectors.themeMode(store.getState())).toBe('light');
    actions.setThemeAttribute('dark');
    expect(selectors.themeMode(store.getState())).toBe('dark');
  });
});
