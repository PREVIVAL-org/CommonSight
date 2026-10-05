/**
 * Traffic on the map: road sections as lines, notice locations as circles (K-05, K-06).
 */
import trafficAt from '@contract/fixtures/traffic-AT.json';
import type { TrafficNoticeItem } from '@sdk/map';
import { asSnapshot, testRenderContext } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { JAM_COLOR, trafficLayer } from './map';

const items = asSnapshot(trafficAt).items as TrafficNoticeItem[];

/** The point drawn for a located notice of this category, without its road section. */
function pointOf(category: TrafficNoticeItem['category']) {
  const base = items.find((item) => item.lat !== undefined);
  if (base === undefined) throw new Error('traffic fixture without a located notice');
  const [feature] = trafficLayer.toFeatures(
    [{ ...base, geometry: undefined, category }],
    testRenderContext('traffic'),
  );
  if (feature === undefined) throw new Error('no point drawn');
  return feature.properties;
}

describe('traffic on the map (K-05, K-06)', () => {
  it('draws the road section as a line and the location as a point', () => {
    const withLine = items.find((item) => item.geometry?.type === 'LineString' && item.lat !== undefined);
    if (withLine === undefined) throw new Error('traffic fixture without a located road section');
    expect(
      trafficLayer
        .toFeatures([withLine], testRenderContext('traffic'))
        .map((feature) => feature.geometry.type),
    ).toEqual(['LineString', 'Point']);
  });

  it('draws jams red, larger and on top; closures and roadworks in the layer colour', () => {
    const context = testRenderContext('traffic');
    const jam = pointOf('jam');
    const closure = pointOf('closure');
    expect(jam.color).toBe(JAM_COLOR);
    expect(closure.color).toBe(context.layerColor);
    expect(pointOf('roadworks').color).toBe(context.layerColor);
    expect(jam.radius).toBeGreaterThan(closure.radius);
    expect(jam.priority).toBeGreaterThan(closure.priority);
  });

  it('draws only traffic notices', () => {
    const features = trafficLayer.toFeatures(items, testRenderContext('traffic'));
    expect(features.length).toBeGreaterThan(0);
    expect(features.every((feature) => items.some((item) => item.id === feature.properties.itemId))).toBe(
      true,
    );
  });
});
