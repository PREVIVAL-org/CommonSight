/**
 * Border zone beyond DACH: loading via the status section "border", items near the selection, list section and
 * country in the card title.
 */
import { describe, expect, it } from 'vitest';
import type { StatusResponse } from '../../src/contract/types';
import { nearSelection } from '../../src/domain/border-filter';
import { parsePersistedSettings } from '../../src/domain/persisted-state';
import { changedVersions } from '../../src/domain/changed-versions';
import { createSelectors } from '../../src/state/selectors';
import { formats, t } from '../support/deps';
import { snapshot, snapshotFor, statusAT } from '../support/fixtures';
import { createTestStore, loadInto } from '../support/store';
import { itemOf } from '../../src/state/selectors/data-selectors';

const border = snapshot('weather-border');
const ids = (items: readonly { id: string }[]): string[] => items.map((item) => item.id);

function setup() {
  const bundle = createTestStore('AT');
  loadInto(bundle, statusAT(), [snapshot('weather-AT'), border]);
  const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));
  return { ...bundle, selectors };
}

describe('border zone', () => {
  it('loads the snapshots of the status section "border" once', () => {
    const entry = {
      ...statusAT().layers.weather!,
      scope: 'border' as const,
      version: 'b0rd3r00',
      url: 'data/v1/border/weather.b0rd3r00.json',
    };
    const status: StatusResponse = { ...statusAT(), border: { weather: entry } };
    const refs = changedVersions(status, {}, ['weather']);
    expect(refs.map((ref) => ref.key)).toEqual(['AT/weather', 'border/weather']);
    expect(
      changedVersions(status, { 'AT/weather': refs[0]!.version, 'border/weather': 'b0rd3r00' }, ['weather']),
    ).toEqual([]);
  });

  it('finds an item of the border zone for "Auf Karte anzeigen" like one of the country', () => {
    const { store } = setup();
    expect(itemOf(store.getState(), 'weather', 'weather:it-bolzano')?.title).toBe('Bolzano/Bozen');
    expect(itemOf(store.getState(), 'weather', 'at-wien')?.title).toBe('Wien');
    expect(itemOf(store.getState(), 'weather', 'fehlt')).toBeUndefined();
    // A neighbour loaded for "Grenzgebiet" (e.g. München from the German snapshot, seen from Austria).
    const bundle = createTestStore('AT');
    loadInto(bundle, statusAT(), [snapshot('weather-AT'), snapshotFor('weather-AT', 'DE')]);
    expect(itemOf(bundle.store.getState(), 'weather', 'DE-at-wien')?.title).toBe('Wien');
  });

  it('picks the items near the selected countries, or near the region if one is chosen', () => {
    expect(ids(nearSelection(border.items, ['AT'], null))).toEqual(['weather:it-bolzano']);
    expect(ids(nearSelection(border.items, ['CH'], null))).toEqual([
      'weather:fr-strasbourg',
      'weather:it-milano',
      'weather:it-bolzano',
    ]);
    expect(ids(nearSelection(border.items, ['CH'], 'CH-TI'))).toEqual(['weather:it-milano']);
    expect(ids(nearSelection(border.items, ['AT'], 'AT-9'))).toEqual([]);
  });

  it('shows the section "Grenzgebiet" in the list, with the country in the title, without counting it', () => {
    const { store, actions, selectors } = setup();
    const list = selectors.list(store.getState(), 'measurements');
    expect(list.border.map((view) => view.base.title)).toEqual(['Bolzano/Bozen (Italien)']);
    expect(list.borderRows).toHaveLength(1);
    expect(list.views.map((view) => view.base.title)).not.toContain('Bolzano/Bozen (Italien)');
    expect(list.status.countText).toBe(selectors.list(store.getState(), 'measurements').status.countText);
    actions.choosePlace('AT:AT-7');
    expect(selectors.list(store.getState(), 'measurements').border.map((view) => view.base.id)).toEqual([
      'weather:it-bolzano',
    ]);
    actions.choosePlace('AT:AT-9');
    expect(selectors.list(store.getState(), 'measurements').border).toEqual([]);
  });

  it('switch "Grenzgebiet" off: no border items on map and list, no loading, setting kept', () => {
    const { store, actions, selectors } = setup();
    actions.setBorderZone(false);
    const state = store.getState();
    expect(selectors.list(state, 'measurements').border).toEqual([]);
    expect(selectors.borderAll(state, 'weather')).toEqual([]);
    const entry = {
      ...statusAT().layers.weather!,
      scope: 'border' as const,
      version: 'b0rd3r00',
      url: 'data/v1/border/weather.b0rd3r00.json',
    };
    const status: StatusResponse = { ...statusAT(), border: { weather: entry } };
    expect(changedVersions(status, {}, ['weather'], false).map((ref) => ref.key)).toEqual(['AT/weather']);
    expect(parsePersistedSettings({ borderZone: false })).toEqual({ borderZone: false });
    expect(parsePersistedSettings({ borderZone: 'nein' })).toEqual({});
    actions.setBorderZone(true);
    expect(selectors.list(store.getState(), 'measurements').border).toHaveLength(1);
  });

  it('counts the DACH neighbours near the selection to the border area, before the foreign items', () => {
    const bundle = createTestStore('DE');
    const austria = snapshot('weather-AT');
    const [first, ...rest] = austria.items;
    const nearGermany = { ...first!, near: { countries: ['DE' as const], regionIds: ['DE-BY'] } };
    loadInto(bundle, null, [{ ...austria, items: [nearGermany, ...rest] }, border]);
    const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));
    const titles = selectors
      .list(bundle.store.getState(), 'measurements')
      .border.map((view) => view.base.title);
    expect(titles[0]).toBe(`${first!.title} (Österreich)`);
    expect(titles.slice(1)).toEqual(['Strasbourg (Frankreich)', 'Bolzano/Bozen (Italien)']);
    bundle.actions.choosePlace('DE:DE-BW');
    expect(
      selectors.list(bundle.store.getState(), 'measurements').border.map((view) => view.base.id),
    ).toEqual(['weather:fr-strasbourg']);
  });

  it('with a region, counts the other regions of its country and the other countries to the border area', () => {
    const bundle = createTestStore('AT');
    const austria = snapshot('weather-AT');
    const [vienna, lowerAustria] = austria.items;
    const near = (regionIds: string[]) => ({ countries: ['AT' as const], regionIds });
    const items = [
      { ...vienna!, near: near(['AT-3', 'AT-9']) },
      { ...lowerAustria!, near: near(['AT-3', 'AT-9']) },
    ];
    const swiss = snapshot('weather-AT');
    const zurich = {
      ...swiss.items[0]!,
      id: 'ch-zuerich',
      title: 'Zürich',
      regionIds: ['CH-ZH'],
      near: near(['AT-8']),
    };
    loadInto(bundle, null, [{ ...austria, items }, { ...swiss, scope: 'CH', items: [zurich] }, border]);
    const selectors = createSelectors({ t, format: formats }, new Intl.Collator('de'));

    bundle.actions.choosePlace('AT:AT-9');
    const list = selectors.list(bundle.store.getState(), 'measurements');
    expect(list.border.map((view) => view.base.title)).toEqual(['Modellpunkt Niederösterreich']);
    expect(list.views.map((view) => view.base.id)).toEqual(['at-wien']);
    bundle.actions.choosePlace('AT:AT-8');
    expect(selectors.borderAll(bundle.store.getState(), 'weather').map((item) => item.id)).toEqual([
      'ch-zuerich',
    ]);
  });

  it('draws the border items near the selection on the map and finds them for the detail sheet', () => {
    const { store, selectors } = setup();
    expect(selectors.borderAll(store.getState(), 'weather').map((item) => item.id)).toEqual([
      'weather:it-bolzano',
    ]);
    expect(selectors.itemView(store.getState(), 'weather', 'weather:fr-strasbourg')?.base.title).toBe(
      'Strasbourg (Frankreich)',
    );
    expect(selectors.scoped(store.getState(), 'weather').matched.map((item) => item.id)).not.toContain(
      'weather:fr-strasbourg',
    );
  });
});
