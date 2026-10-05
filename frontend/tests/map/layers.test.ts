/**
 * Map layers as the core draws them (pure): point style, measurement points, order and layer specifications of the
 * renderers the layer packages bring (K-05, K-10, K-11); what a layer draws is tested in its package.
 */
import { describe, expect, it } from 'vitest';
import { LAYER_IDS, layerMeta } from '../../src/contract/master-data';
import type { LayerId } from '../../src/contract/types';
import type { RenderContext } from '../../src/map/layers/feature-model';
import { measurementFeatures } from '../../src/map/layers/measurement-features';
import { pointStyle } from '../../src/map/layers/point-style';
import { regionLayers } from '../../src/map/layers/region-layer';
import { DRAW_ORDER, layerRenderers } from '../../src/map/layers/renderers';
import { LEVEL_COLORS } from '../../src/theme/fixed';
import { snapshot } from '../support/fixtures';
import { NOW } from '../support/deps';

function context(layer: LayerId): RenderContext {
  return { layer, layerColor: '#398ed4', labelColor: '#333333', labelHalo: '#ffffff', nowMs: NOW };
}

describe('point style (K-05)', () => {
  it('draws high and elevated larger with a light halo and on top', () => {
    const high = pointStyle('high', '#398ed4');
    const elevated = pointStyle('elevated', '#398ed4');
    const normal = pointStyle('normal', '#398ed4');
    const unknown = pointStyle('unknown', '#398ed4');
    expect(high).toMatchObject({ color: LEVEL_COLORS.high, stroke: '#ffffff', radius: 8 });
    expect(elevated).toMatchObject({ color: LEVEL_COLORS.elevated, radius: 7 });
    expect(high.priority).toBeGreaterThan(elevated.priority);
    expect(elevated.priority).toBeGreaterThan(normal.priority);
    expect(normal.color).toBe('#398ed4');
    expect(unknown).toMatchObject({ color: LEVEL_COLORS.unknown, opacity: 0.25 });
    expect(unknown.priority).toBeLessThan(normal.priority);
  });
});

describe('layer renderers', () => {
  it('measurement points take the rechecked assessment for their colour (K-05, B-03)', () => {
    const colors = measurementFeatures(snapshot('water-DE').items, context('water')).map(
      (feature) => feature.properties.color,
    );
    expect(colors).toEqual([
      LEVEL_COLORS.elevated,
      LEVEL_COLORS.unknown,
      LEVEL_COLORS.unknown,
      LEVEL_COLORS.unknown,
    ]);
  });

  it('come from the layers on the map; layers without a map are not drawn (K-10)', () => {
    for (const layer of LAYER_IDS) {
      expect(layerRenderers[layer] !== undefined).toBe(layerMeta(layer).onMap);
    }
  });

  it('describe data-driven MapLibre layers bound to the source', () => {
    for (const [layer, renderer] of Object.entries(layerRenderers)) {
      if (renderer === undefined) continue;
      const specs = renderer.styleLayers(layer, context(layer));
      expect(specs.length).toBeGreaterThan(0);
      expect(
        specs.every((spec) => 'source' in spec && spec.source === layer && spec.id.startsWith(layer)),
      ).toBe(true);
    }
  });

  it('are drawn in the order of their rank, the region outline among them', () => {
    expect(DRAW_ORDER).toContain('region');
    expect(DRAW_ORDER.filter((id) => id !== 'region').sort()).toEqual(Object.keys(layerRenderers).sort());
  });

  it('draws the region outline dashed (U-13)', () => {
    const [fill, line] = regionLayers('region', '#1d6cb2');
    expect(fill?.type).toBe('fill');
    expect(line?.type === 'line' && line.paint?.['line-dasharray']).toEqual([2, 1.5]);
  });
});
