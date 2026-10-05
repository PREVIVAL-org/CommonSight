/**
 * Pollen on the map: every place with a model value, labelled with place and rounded concentration.
 */
import pollenAt from '@contract/fixtures/pollen-AT.json';
import { asSnapshot, testRenderContext } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { pollenLabel, pollenLayer } from './map';

describe('pollen on the map', () => {
  it('labels place and rounded concentration', () => {
    expect(pollenLabel('Wien', 5.5)).toBe('Wien 6');
    expect(pollenLabel('Graz', 0)).toBe('Graz 0');
  });

  it('draws every place of the snapshot', () => {
    const items = asSnapshot(pollenAt).items;
    const features = pollenLayer.toFeatures(items, testRenderContext('pollen'));
    expect(features.map((feature) => feature.properties.itemId)).toEqual(items.map((item) => item.id));
  });
});
