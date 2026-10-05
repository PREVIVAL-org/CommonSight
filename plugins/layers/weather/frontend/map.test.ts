/**
 * Weather on the map: labels with place and rounded temperature (K-04).
 */
import weatherAt from '@contract/fixtures/weather-AT.json';
import { asSnapshot, testRenderContext } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { weatherLabel, weatherLayer } from './map';

describe('weather on the map (K-04)', () => {
  it('labels place and rounded temperature, without the prefix of model points', () => {
    expect(weatherLabel('Modellpunkt Saarland', -0.4)).toBe('Saarland 0°');
    expect(weatherLabel('Zürich', -3.6)).toBe('Zürich -4°');
  });

  it('draws every place with its label', () => {
    const items = asSnapshot(weatherAt).items;
    const features = weatherLayer.toFeatures(items, testRenderContext('weather'));
    expect(features).toHaveLength(items.length);
    expect(features[0]?.properties.label).toBe('Wien 24°');
  });
});
