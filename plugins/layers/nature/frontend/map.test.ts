/**
 * Earthquakes on the map: circles that grow with the magnitude (K-05).
 */
import natureAt from '@contract/fixtures/nature-AT.json';
import type { EarthquakeItem } from '@sdk/map';
import { asSnapshot, testRenderContext } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { magnitudeRadius, natureLayer } from './map';

describe('earthquakes on the map (K-05)', () => {
  it('grow with the magnitude within 4 to 16 pixels', () => {
    expect(magnitudeRadius(9)).toBe(16);
    expect(magnitudeRadius(0)).toBe(4);
    expect(magnitudeRadius(3.5)).toBe(10);
  });

  it('draw each quake with its radius', () => {
    const items = asSnapshot(natureAt).items as EarthquakeItem[];
    const features = natureLayer.toFeatures(items, testRenderContext('nature'));
    expect(features.map((feature) => feature.properties.radius)).toEqual(
      items.map((item) => magnitudeRadius(item.magnitude)),
    );
  });
});
