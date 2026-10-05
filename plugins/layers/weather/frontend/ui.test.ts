/**
 * Weather tile: the first model value of the selected region (U-27).
 */
import weatherAt from '@contract/fixtures/weather-AT.json';
import type { TileInput } from '@sdk/ui';
import { asSnapshot, TEST_NOW, testFormat } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { ui } from './ui';

function input(matched: TileInput['matched']): TileInput {
  return { snapshot: undefined, matched, reference: null, nowMs: TEST_NOW, format: testFormat() };
}

describe('weather tile (U-27)', () => {
  it('shows °C, place and weather of the first place and opens it', () => {
    const [first] = asSnapshot(weatherAt).items;
    const value = ui.tile?.value(input(asSnapshot(weatherAt).items));
    expect(value).toMatchObject({ value: '23,6 °C', itemId: first?.id });
    expect(value?.detail).toMatch(/^Wien · \S/);
  });

  it('is empty without a model value', () => {
    expect(ui.tile?.value(input([]))).toBeNull();
  });
});
