/**
 * Space weather tile: Kp index with its course and the NOAA scales (U-27).
 */
import spaceGlobal from '@contract/fixtures/space-global.json';
import waterDe from '@contract/fixtures/water-DE.json';
import type { Snapshot, TileInput, TileValue } from '@sdk/ui';
import { asSnapshot, TEST_NOW, testFormat } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { isSpaceStats, ui } from './ui';

function input(snapshot: Snapshot | undefined): TileInput {
  return { snapshot, matched: [], reference: null, nowMs: TEST_NOW, format: testFormat() };
}

describe('space weather tile (U-27)', () => {
  it('recognizes its key figures', () => {
    expect(isSpaceStats(asSnapshot(spaceGlobal).stats)).toBe(true);
    expect(isSpaceStats(asSnapshot(waterDe).stats)).toBe(false);
  });

  const snapshot = asSnapshot(spaceGlobal);
  if (!isSpaceStats(snapshot.stats)) throw new Error('space fixture without key figures');
  const stats = snapshot.stats;

  function shown(of: Snapshot): TileValue {
    const value = ui.tile?.value(input(of));
    if (value === undefined || value === null) throw new Error('space tile without a value');
    return value;
  }

  it('shows Kp, the scales and the course of the data', () => {
    const value = shown(snapshot);
    expect(value.value).toMatch(/^Kp \d/);
    expect(value.detail).toMatch(/^G[\d-] · R[\d-] · S[\d-]$/);
    expect(value.highlight).toBe((stats.kp ?? 0) >= 5);
    expect(value.history).toMatchObject({ values: stats.history, max: 9 });
  });

  it('names the course in German', () => {
    const label = shown(snapshot).history?.label;
    expect(label === undefined ? null : testFormat().text(label)).toBe(
      'Kp-Verlauf der letzten Messintervalle',
    );
  });

  it('is highlighted from a storm and shows missing scales as a dash', () => {
    const storm = shown({ ...snapshot, stats: { ...stats, kp: 5.33, G: 1, R: null } });
    expect(storm).toMatchObject({ value: 'Kp 5,33', highlight: true });
    expect(storm.detail).toMatch(/^G1 · R- · S/);
  });

  it('is empty without key figures', () => {
    expect(ui.tile?.value(input(undefined))).toBeNull();
  });
});
