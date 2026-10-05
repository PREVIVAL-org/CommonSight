/**
 * Air tile: the first model value of the selected region, highlighted above an EU-AQI of 40 (U-27).
 */
import airAt from '@contract/fixtures/air-AT.json';
import type { Item, TileInput } from '@sdk/ui';
import { asSnapshot, TEST_NOW, testFormat } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { AIR_HIGHLIGHT_ABOVE, ui } from './ui';

function input(matched: readonly Item[]): TileInput {
  return { snapshot: undefined, matched, reference: null, nowMs: TEST_NOW, format: testFormat() };
}

describe('air tile (U-27)', () => {
  const [first] = asSnapshot(airAt).items;

  it('shows the index without decimals', () => {
    expect(ui.tile?.value(input(asSnapshot(airAt).items))).toMatchObject({
      value: '35 EU-AQI',
      highlight: false,
    });
  });

  it('is highlighted above 40', () => {
    const polluted = { ...first, value: AIR_HIGHLIGHT_ABOVE + 1 } as Item;
    expect(ui.tile?.value(input([polluted]))?.highlight).toBe(true);
    const limit = { ...first, value: AIR_HIGHLIGHT_ABOVE } as Item;
    expect(ui.tile?.value(input([limit]))?.highlight).toBe(false);
  });
});
