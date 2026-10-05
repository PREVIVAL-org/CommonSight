/**
 * Place, key figures, stored settings and clock offset (K-09, U-12, U-27, U-60, Architecture 6.2).
 */
import { describe, expect, it } from 'vitest';
import { cities, regions } from '../../src/contract/master-data';
import { clockOffsetMs } from '../../src/domain/clock-offset';
import { boundingBox, focusTarget, hasLocation } from '../../src/domain/item-location';
import { referencePoint } from '../../src/domain/metrics';
import { distanceKm, nearestItem } from '../../src/domain/nearest-item';
import { parsePersistedSettings } from '../../src/domain/persisted-state';
import { findRegion, regionOptions } from '../../src/domain/region-options';
import { snapshot } from '../support/fixtures';
import { cameraFor } from '../../src/domain/map-camera';
import { createTestStore } from '../support/store';

describe('focusTarget', () => {
  const [gewitter, wind, heat] = snapshot('warnings-AT').items;

  it('uses the bounding box of polygons and multipolygons', () => {
    expect(gewitter && focusTarget(gewitter)).toEqual({ type: 'bounds', bbox: [16.2, 48.1, 16.6, 48.3] });
    expect(wind && focusTarget(wind)).toEqual({ type: 'bounds', bbox: [11, 47.1, 11.5, 47.4] });
  });

  it('uses the point for items with lat/lon and nothing without location', () => {
    const [station] = snapshot('water-DE').items;
    expect(station && focusTarget(station)).toEqual({ type: 'point', lon: 6.9631, lat: 50.9366 });
    expect(heat && hasLocation(heat)).toBe(false);
    expect(boundingBox([])).toBeNull();
  });
});

describe('metrics', () => {
  it('takes the reference point of the region, otherwise the first place of the country', () => {
    const stations = snapshot('radiation-AT').items;
    const tirol = findRegion(regions, 'AT-7');
    const tirolPoint = referencePoint(tirol, null);
    expect(tirolPoint === null ? null : nearestItem(stations, tirolPoint)?.title).toBe('Innsbruck');
    const firstAustrianCity = cities.find((city) => city.country === 'AT') ?? null;
    const cityPoint = referencePoint(null, firstAustrianCity);
    expect(cityPoint === null ? null : nearestItem(stations, cityPoint)?.title).toBe('Wien-Hohe Warte');
    expect(referencePoint(null, null)).toBeNull();
  });

  it('computes great circle distances', () => {
    expect(distanceKm({ lon: 16.37, lat: 48.21 }, { lon: 11.4, lat: 47.27 })).toBeGreaterThan(370);
    expect(distanceKm({ lon: 16.37, lat: 48.21 }, { lon: 16.37, lat: 48.21 })).toBe(0);
  });
});

describe('regionOptions', () => {
  it('sorts the regions of a country alphabetically (U-12)', () => {
    const names = regionOptions(regions, 'AT', new Intl.Collator('de')).map((region) => region.name);
    expect(names[0]).toBe('Burgenland');
    expect(names).toHaveLength(9);
    expect(names.indexOf('Kärnten')).toBeLessThan(names.indexOf('Niederösterreich'));
  });
});

describe('parsePersistedSettings', () => {
  it('keeps valid values and ignores the credits state saved by former versions', () => {
    expect(
      parsePersistedSettings({
        country: 'CH',
        regionId: 'CH-ZH',
        activeLayers: ['water', 'air'],
        mapNoticeHidden: true,
        attributionHidden: false,
      }),
    ).toEqual({
      country: 'CH',
      regionId: 'CH-ZH',
      activeLayers: ['water', 'air'],
      mapNoticeHidden: true,
    });
  });

  it('drops invalid values one by one (U-60)', () => {
    expect(
      parsePersistedSettings({
        country: 'FR',
        regionId: 'CH-ZH',
        activeLayers: ['water', 'bogus', 'news', 'water'],
        mapNoticeHidden: 'yes',
        attributionHidden: 1,
      }),
    ).toEqual({
      activeLayers: ['water'],
    });
    expect(parsePersistedSettings({ country: 'AT', regionId: 'CH-ZH' })).toEqual({ country: 'AT' });
    expect(parsePersistedSettings({ country: 'AT', regionId: null })).toEqual({
      country: 'AT',
      regionId: null,
    });
    expect(parsePersistedSettings('kaputt')).toEqual({});
    expect(parsePersistedSettings(null)).toEqual({});
  });
});

describe('clockOffsetMs', () => {
  it('returns server minus browser time and 0 for invalid server time', () => {
    expect(clockOffsetMs('2026-09-28T12:00:10Z', Date.parse('2026-09-28T12:00:00Z'))).toBe(10_000);
    expect(clockOffsetMs('nie', 5)).toBe(0);
  });
});

describe('map section of the last visit (U-60)', () => {
  it('stores centre and zoom with the place, rounded, and restores them only for the same place', () => {
    const { store, actions } = createTestStore('AT');
    actions.setRegion('AT-9');
    actions.setMapView({ lon: 16.373819123, lat: 48.208174456, zoom: 9.123456 });
    const saved = store.getState().selection.mapView;
    expect(saved).toEqual({ lon: 16.37382, lat: 48.20817, zoom: 9.12, place: 'AT|AT-9' });
    expect(cameraFor(saved, 'AT', 'AT-9')).toEqual(saved);
    expect(cameraFor(saved, 'AT', null)).toBeNull();
    expect(cameraFor(saved, 'DE', null)).toBeNull();
  });

  it('keeps only a valid stored section', () => {
    const view = { lon: 9.5, lat: 47.2, zoom: 8, place: 'CH|' };
    expect(parsePersistedSettings({ mapView: view })).toEqual({ mapView: view });
    expect(parsePersistedSettings({ mapView: { ...view, lat: 80 } })).toEqual({});
    expect(parsePersistedSettings({ mapView: { ...view, zoom: 30 } })).toEqual({});
    expect(parsePersistedSettings({ mapView: { ...view, place: 1 } })).toEqual({});
    expect(parsePersistedSettings({ mapView: 'Wien' })).toEqual({});
  });
});
