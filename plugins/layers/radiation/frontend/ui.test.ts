/**
 * Radiation: legend with the display thresholds of the data (U-25) and the tile with the station nearest to the
 * reference point, with a hint for older values (U-27).
 */
import radiationDe from '@contract/fixtures/radiation-DE.json';
import type { MeasurementItem, TileInput } from '@sdk/ui';
import { asSnapshot, TEST_NOW, testFormat } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { isOlderThan, TILE_AGE_HINT_MS, ui } from './ui';

const snapshot = asSnapshot(radiationDe);
const stations = snapshot.items as MeasurementItem[];

function input(reference: TileInput['reference'], nowMs: number = TEST_NOW): TileInput {
  return { snapshot, matched: [], reference, nowMs, format: testFormat(nowMs) };
}

describe('radiation legend (U-25)', () => {
  it('names the thresholds of the data, or that they are pending', () => {
    const format = testFormat();
    const [line] = ui.legend?.lines({ countries: ['DE'], snapshot }) ?? [];
    expect(line?.key).toBe('layer.radiation.legend.scale');
    expect(format.text(line ?? { key: '' })).toContain('0,3');
    expect(ui.legend?.lines({ countries: ['DE'], snapshot: undefined })).toEqual([
      { key: 'layer.radiation.legend.pending' },
    ]);
  });
});

describe('radiation tile (U-27)', () => {
  const [, second] = stations;
  if (second?.lon === undefined || second.lat === undefined || second.time === undefined) {
    throw new Error('radiation fixture without a located station');
  }
  const at = { lon: second.lon, lat: second.lat };
  const measured = Date.parse(second.time);

  it('shows the station nearest to the reference point and opens it', () => {
    expect(ui.tile?.value(input(at, measured))).toMatchObject({ detail: second.title, itemId: second.id });
    expect(ui.tile?.value(input(null))).toBeNull();
  });

  it('marks values older than 6 h as "älterer Wert"', () => {
    expect(ui.tile?.value(input(at, measured + 3600_000))?.hint).toBeUndefined();
    expect(ui.tile?.value(input(at, measured + TILE_AGE_HINT_MS + 1))?.hint).toEqual({
      key: 'layer.radiation.tile.olderValue',
    });
  });

  it('treats values without or with a broken time as old', () => {
    const now = Date.parse('2026-09-28T12:00:00Z');
    expect(isOlderThan('2026-09-28T05:00:00Z', TILE_AGE_HINT_MS, now)).toBe(true);
    expect(isOlderThan('2026-09-28T07:00:00Z', TILE_AGE_HINT_MS, now)).toBe(false);
    expect(isOlderThan(undefined, 1, now)).toBe(true);
    expect(isOlderThan('kaputt', 1, now)).toBe(true);
  });
});
