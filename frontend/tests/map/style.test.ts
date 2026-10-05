/**
 * Map style from theming colors (pure): palette, mask, PMTiles source and the case without basemap
 * (T-04, K-01, K-12).
 */
import { describe, expect, it } from 'vitest';
import type { MapPalette } from '../../src/map/style';
import {
  BASEMAP_SOURCE,
  BORDER_SHADE_SOURCE,
  buildBaseStyle,
  flavorFor,
  MASK_SOURCE,
  PALETTE_VARIABLES,
} from '../../src/map/style';

const palette: MapPalette = {
  land: 'rgb(1, 1, 1)',
  water: 'rgb(2, 2, 2)',
  park: 'rgb(3, 3, 3)',
  building: 'rgb(4, 4, 4)',
  road: 'rgb(5, 5, 5)',
  roadMajor: 'rgb(6, 6, 6)',
  boundary: 'rgb(7, 7, 7)',
  label: 'rgb(8, 8, 8)',
  labelHalo: 'rgb(9, 9, 9)',
  mask: 'rgb(10, 10, 10)',
  region: 'rgb(11, 11, 11)',
};

const inputs = {
  palette,
  mode: 'light' as const,
  tilesUrl: 'https://example.org/commonsight/tiles/dach-2026-10.pmtiles',
  glyphsUrl: 'https://example.org/commonsight/map/glyphs/{fontstack}/{range}.pbf',
  maskUrl: 'https://example.org/commonsight/map/dach-mask.geojson',
  borderShadeUrl: 'https://example.org/commonsight/map/border-shade.geojson',
  attribution: '© OpenStreetMap',
};

describe('buildBaseStyle', () => {
  it('uses the pmtiles archive, self-hosted glyphs and sprites and the mask on top', () => {
    const style = buildBaseStyle(inputs);
    expect(style.sources[BASEMAP_SOURCE]).toMatchObject({
      type: 'vector',
      url: `pmtiles://${inputs.tilesUrl}`,
      attribution: '© OpenStreetMap',
    });
    expect(style.sources[MASK_SOURCE]).toEqual({ type: 'geojson', data: inputs.maskUrl });
    expect(style.sources[BORDER_SHADE_SOURCE]).toEqual({ type: 'geojson', data: inputs.borderShadeUrl });
    // The border zone dimmed directly below the opaque mask.
    expect(style.layers.at(-2)).toMatchObject({
      id: 'border-shade-fill',
      paint: { 'fill-color': palette.mask, 'fill-opacity': 0.45 },
    });
    expect(style.glyphs).toBe(inputs.glyphsUrl);
    expect(style.sprite).toBeUndefined();
    expect(style.layers[0]).toMatchObject({ id: 'background', paint: { 'background-color': palette.mask } });
    expect(style.layers.at(-1)).toMatchObject({
      id: 'dach-mask-fill',
      paint: { 'fill-color': palette.mask },
    });
    expect(style.layers.length).toBeGreaterThan(20);
    expect(style.layers.filter((layer) => layer.id === 'background')).toHaveLength(1);
  });

  it('without archive draws only background and mask (K-12)', () => {
    const style = buildBaseStyle({ ...inputs, tilesUrl: null });
    expect(Object.keys(style.sources)).toEqual([MASK_SOURCE]);
    expect(style.layers.map((layer) => layer.id)).toEqual(['background', 'dach-mask-fill']);
  });

  it('maps the palette onto the protomaps flavour', () => {
    const flavor = flavorFor(palette, 'dark');
    expect(flavor).toMatchObject({
      earth: palette.land,
      water: palette.water,
      park_a: palette.park,
      highway: palette.roadMajor,
      minor_a: palette.road,
      city_label: palette.label,
      city_label_halo: palette.labelHalo,
      boundaries: palette.boundary,
    });
  });

  it('names a CSS variable for every palette entry', () => {
    expect(Object.keys(PALETTE_VARIABLES).sort()).toEqual(Object.keys(palette).sort());
  });
});
