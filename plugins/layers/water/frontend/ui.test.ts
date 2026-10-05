/**
 * Water legend: the thresholds of the gauges per country of the selection (U-25).
 */
import { testFormat } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { ui } from './ui';

describe('water legend (U-25)', () => {
  it('explains the colors, then the thresholds of each selected country', () => {
    const lines = ui.legend?.lines({ countries: ['DE', 'AT'], snapshot: undefined }) ?? [];
    expect(lines.map((line) => line.key)).toEqual([
      'layer.water.legend.default',
      'layer.water.legend.DE',
      'layer.water.legend.AT',
    ]);
  });

  it('has a text for every country', () => {
    const format = testFormat();
    const lines = ui.legend?.lines({ countries: ['DE', 'AT', 'CH'], snapshot: undefined }) ?? [];
    expect(lines.every((line) => format.text(line) !== line.key)).toBe(true);
  });
});
