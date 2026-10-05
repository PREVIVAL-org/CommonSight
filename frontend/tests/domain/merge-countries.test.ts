/**
 * Selection "Alle" (all): countries of the selection, place value, merging of status and snapshots (ADR 0037).
 */
import { describe, expect, it } from 'vitest';
import type { LayerStatus } from '../../src/contract/types';
import {
  choiceBounds,
  countriesOf,
  parseCountryChoice,
  referenceCountry,
} from '../../src/domain/country-choice';
import { mergeLayerStatus, mergeSnapshots } from '../../src/domain/merge-countries';
import { parsePersistedSettings } from '../../src/domain/persisted-state';
import { decodePlace, encodePlace } from '../../src/domain/place-choice';
import { snapshotKeysOf } from '../../src/domain/snapshot-key';
import { snapshot, snapshotFor, statusAT, statusFor } from '../support/fixtures';

function entry(status: LayerStatus['status'], changes: Partial<LayerStatus> = {}): LayerStatus {
  const base = statusAT().layers.weather as LayerStatus;
  return { ...base, status, ...changes };
}

describe('country choice', () => {
  it('covers all countries with "Alle" and uses Germany as reference', () => {
    expect(countriesOf('ALL')).toEqual(['DE', 'AT', 'CH']);
    expect(countriesOf('AT')).toEqual(['AT']);
    expect(countriesOf('ALL')).toBe(countriesOf('ALL'));
    expect(referenceCountry('ALL')).toBe('DE');
    expect(referenceCountry('CH')).toBe('CH');
    expect(parseCountryChoice('ALL')).toBe('ALL');
    expect(parseCountryChoice('FR')).toBeNull();
  });

  it('spans the bounds of all countries', () => {
    const [west, south, east, north] = choiceBounds('ALL');
    expect(west).toBeLessThan(6.1);
    expect(south).toBeLessThan(46);
    expect(east).toBeGreaterThan(17);
    expect(north).toBeGreaterThan(55);
  });

  it('encodes "Alle" without region and keeps it in the settings', () => {
    expect(encodePlace({ country: 'ALL', regionId: null })).toBe('ALL');
    expect(decodePlace('ALL')).toEqual({ country: 'ALL', regionId: null });
    expect(decodePlace('ALL:AT-9')).toBeNull();
    expect(parsePersistedSettings({ country: 'ALL', regionId: 'AT-9' })).toEqual({ country: 'ALL' });
  });

  it('lists one snapshot key per country and a global one only once', () => {
    expect(snapshotKeysOf('water', 'ALL')).toEqual(['DE/water', 'AT/water', 'CH/water']);
    expect(snapshotKeysOf('news', 'ALL')).toEqual(['global/news']);
  });
});

describe('mergeLayerStatus', () => {
  it('keeps equal states and marks mixed ones as partial', () => {
    expect(mergeLayerStatus([entry('ok'), entry('ok')])?.status).toBe('ok');
    expect(mergeLayerStatus([entry('ok'), entry('error')])?.status).toBe('partial');
    expect(mergeLayerStatus([entry('setup'), entry('setup')])?.status).toBe('setup');
    expect(mergeLayerStatus([])).toBeUndefined();
  });

  it('adds counts and takes the oldest check and the newest source time', () => {
    const merged = mergeLayerStatus([
      entry('ok', {
        itemCount: 2,
        checkedAt: '2026-09-28T11:50:00Z',
        sourceUpdatedAt: '2026-09-28T11:00:00Z',
      }),
      entry('ok', {
        itemCount: 5,
        checkedAt: '2026-09-28T11:55:00Z',
        sourceUpdatedAt: '2026-09-28T11:40:00Z',
      }),
    ]);
    expect(merged).toMatchObject({
      itemCount: 7,
      checkedAt: '2026-09-28T11:50:00Z',
      sourceUpdatedAt: '2026-09-28T11:40:00Z',
      url: null,
    });
  });

  it('passes global layers through unchanged', () => {
    const news = statusFor('DE').layers.news as LayerStatus;
    expect(mergeLayerStatus([news, statusAT().layers.news as LayerStatus])).toBe(news);
  });
});

describe('mergeSnapshots', () => {
  it('joins items in country order and drops duplicates', () => {
    const at = snapshot('weather-AT');
    const de = snapshotFor('weather-AT', 'DE');
    const merged = mergeSnapshots([de, at, at]);
    expect(merged?.items.map((item) => item.id)).toEqual([
      ...de.items.map((item) => item.id),
      ...at.items.map((item) => item.id),
    ]);
    expect(merged?.status).toBe(de.status);
    expect(mergeSnapshots([at])).toBe(at);
  });
});
