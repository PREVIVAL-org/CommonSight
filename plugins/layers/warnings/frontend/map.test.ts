/**
 * Warnings on the map: areas in the fixed warning colors by awareness level or severity (K-03, K-06).
 */
import warningsAt from '@contract/fixtures/warnings-AT.json';
import type { WarningItem } from '@sdk/map';
import { AWARENESS_COLORS, SEVERITY_COLORS, warningColor } from '@sdk/map';
import { asSnapshot, testRenderContext } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { warningsLayer } from './map';

const items = asSnapshot(warningsAt).items as WarningItem[];

describe('warnings on the map (K-03, K-06)', () => {
  it('take the awareness color, otherwise the severity color', () => {
    const [first] = items;
    if (first === undefined) throw new Error('warnings fixture without warnings');
    expect(warningColor({ ...first, awareness: 'orange' })).toBe(AWARENESS_COLORS.orange);
    const withoutAwareness: WarningItem = { ...first, severity: 'Moderate' };
    delete withoutAwareness.awareness;
    expect(warningColor(withoutAwareness)).toBe(SEVERITY_COLORS.Moderate);
  });

  it('skip warnings without an area', () => {
    const features = warningsLayer.toFeatures(items, testRenderContext('warnings'));
    const drawn = items.filter((item) => item.geometry !== undefined);
    expect(features.map((feature) => feature.properties.itemId)).toEqual(drawn.map((item) => item.id));
    expect(features.map((feature) => feature.properties.color)).toEqual(drawn.map(warningColor));
  });
});
