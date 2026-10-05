/**
 * Pure domain logic of the selection: needed layers, changed versions, activation, refresh interval on return.
 */
import { describe, expect, it } from 'vitest';
import { changedVersions } from '../../src/domain/changed-versions';
import { opensSheetOnActivate, toggleLayer } from '../../src/domain/layer-activation';
import { neededLayers } from '../../src/domain/needed-layers';
import { isRefreshDue, sameLayers } from '../../src/domain/refresh-policy';
import { scopeOf, snapshotKey } from '../../src/domain/snapshot-key';
import { statusAT } from '../support/fixtures';
import { LAYER_IDS } from '../../src/contract/master-data';
import type { LayerId } from '../../src/contract/types';
import { OVERVIEW_LAYER_IDS } from '../../src/domain/layer-slots';

const lists = { measurements: 'water', events: 'nature' } as const;

describe('neededLayers', () => {
  it('overview needs the active layers plus those of map notice, tiles and side panel, in registry order', () => {
    const active: LayerId[] = ['water', 'traffic'];
    expect(neededLayers({ view: 'overview', activeLayers: active, listLayers: lists, sheet: null })).toEqual(
      LAYER_IDS.filter((id) => active.includes(id) || OVERVIEW_LAYER_IDS.includes(id)),
    );
    expect(OVERVIEW_LAYER_IDS.length).toBeGreaterThan(0);
  });

  it('list views need only the chosen layer', () => {
    expect(
      neededLayers({ view: 'measurements', activeLayers: ['warnings'], listLayers: lists, sheet: null }),
    ).toEqual(['water']);
    expect(neededLayers({ view: 'events', activeLayers: [], listLayers: lists, sheet: null })).toEqual([
      'nature',
    ]);
  });

  it('the source overview needs every layer', () => {
    expect(
      neededLayers({ view: 'events', activeLayers: [], listLayers: lists, sheet: { type: 'sources' } }),
    ).toEqual(LAYER_IDS);
  });

  it('a layer or item sheet adds its layer', () => {
    expect(
      neededLayers({
        view: 'events',
        activeLayers: [],
        listLayers: lists,
        sheet: { type: 'item', layer: 'air', itemId: 'x' },
      }),
    ).toEqual(['air', 'nature']);
  });

  it('the unassigned sheet needs all regional layers', () => {
    const layers = neededLayers({
      view: 'events',
      activeLayers: [],
      listLayers: lists,
      sheet: { type: 'unassigned' },
    });
    expect(layers).not.toContain('news');
    expect(layers).not.toContain('space');
    expect(layers).toContain('warnings');
  });
});

describe('changedVersions', () => {
  const status = statusAT();

  it('returns needed layers whose version differs and skips pending layers', () => {
    const refs = changedVersions(status, { 'AT/warnings': '3f9a1c2e' }, ['warnings', 'water', 'space']);
    expect(refs).toEqual([
      {
        key: 'global/space',
        layer: 'space',
        scope: 'global',
        version: 'aa000004',
        url: 'data/v1/global/space.aa000004.json',
      },
    ]);
  });

  it('returns nothing for layers without status entry', () => {
    expect(changedVersions({ ...status, layers: {} }, {}, ['warnings'])).toEqual([]);
  });
});

describe('snapshotKey', () => {
  it('uses the global scope for global layers', () => {
    expect(scopeOf('news', 'DE')).toBe('global');
    expect(snapshotKey('space', 'CH')).toBe('global/space');
    expect(snapshotKey('water', 'CH')).toBe('CH/water');
  });
});

describe('layer activation', () => {
  it('opens the sheet for layers without map display or without data access (U-21)', () => {
    expect(opensSheetOnActivate('space', 'ok')).toBe(true);
    expect(opensSheetOnActivate('traffic', 'setup')).toBe(true);
    expect(opensSheetOnActivate('water', 'ok')).toBe(false);
  });

  it('toggles layers', () => {
    expect(toggleLayer(['water'], 'air')).toEqual(['water', 'air']);
    expect(toggleLayer(['water', 'air'], 'water')).toEqual(['air']);
  });
});

describe('refresh policy', () => {
  it('is due without any data or after one interval', () => {
    expect(isRefreshDue(null, 1000, 60_000)).toBe(true);
    expect(isRefreshDue(0, 59_999, 60_000)).toBe(false);
    expect(isRefreshDue(0, 60_000, 60_000)).toBe(true);
  });

  it('compares layer lists by order and content', () => {
    expect(sameLayers(['a', 'b'], ['a', 'b'])).toBe(true);
    expect(sameLayers(['a', 'b'], ['b', 'a'])).toBe(false);
    expect(sameLayers(['a'], ['a', 'b'])).toBe(false);
  });
});
