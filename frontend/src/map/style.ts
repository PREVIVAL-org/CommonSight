/**
 * Builds the style JSON of the basemap with mask from the resolved theming colors (T-04, K-01, Architecture 8.2);
 * pure.
 */
import type { Flavor } from '@protomaps/basemaps';
import { layers as protomapsLayers, namedFlavor } from '@protomaps/basemaps';
import type { LayerSpecification, StyleSpecification } from 'maplibre-gl';
import type { ThemeMode } from '../theme/theme-mode';

export interface MapPalette {
  land: string;
  water: string;
  park: string;
  building: string;
  road: string;
  roadMajor: string;
  boundary: string;
  label: string;
  labelHalo: string;
  mask: string;
  region: string;
}

/** Mapping of the palette to the map variables of the theming interface (doc/ARCHITECTURE.md 10) (internal names with `--_`). */
export const PALETTE_VARIABLES: Readonly<Record<keyof MapPalette, string>> = {
  land: '--_map-land',
  water: '--_map-water',
  park: '--_map-park',
  building: '--_map-building',
  road: '--_map-road',
  roadMajor: '--_map-road-major',
  boundary: '--_map-boundary',
  label: '--_map-label',
  labelHalo: '--_map-label-halo',
  mask: '--_map-mask',
  region: '--_map-region',
};

export interface BaseStyleInputs {
  palette: MapPalette;
  mode: ThemeMode;
  /** PMTiles archive; `null` = no basemap (K-12). */
  tilesUrl: string | null;
  glyphsUrl: string;
  maskUrl: string;
  /** Border zone beyond DACH (BORDER_ZONE_KM in tools/regions/build.mjs), drawn dimmed so that the border stays recognizable. */
  borderShadeUrl: string;
  attribution: string;
}

export const BASEMAP_SOURCE = 'protomaps';
export const MASK_SOURCE = 'dach-mask';
export const BORDER_SHADE_SOURCE = 'border-shade';

/** Dimming of the border zone: the map stays readable, DACH stands out. */
const BORDER_SHADE_OPACITY = 0.45;

type ColorKey = { [K in keyof Flavor]-?: Flavor[K] extends string | undefined ? K : never }[keyof Flavor];

/** Which colors of the Protomaps flavor come from which map variable; all others keep the flavor's value. */
const FLAVOR_COLORS: Readonly<Record<keyof MapPalette, readonly ColorKey[]>> = {
  land: ['earth'],
  water: ['water'],
  park: ['park_a', 'park_b', 'wood_a', 'wood_b', 'scrub_a', 'scrub_b'],
  building: ['buildings'],
  road: ['minor_a', 'minor_b', 'minor_service', 'link', 'other'],
  roadMajor: ['highway', 'major'],
  boundary: ['boundaries'],
  label: [
    'city_label',
    'state_label',
    'country_label',
    'subplace_label',
    'ocean_label',
    'address_label',
    'roads_label_minor',
    'roads_label_major',
  ],
  labelHalo: [
    'city_label_halo',
    'state_label_halo',
    'subplace_label_halo',
    'address_label_halo',
    'roads_label_minor_halo',
    'roads_label_major_halo',
  ],
  mask: ['background'],
  region: [],
};

/**
 * Land cover exists in the tiles only up to zoom 7 and is strongly generalized; glaciers come out as large white
 * patches (Bernese Oberland, Valais) that vanish from zoom 8 on. They are drawn like bare land, so that the map looks
 * the same at every zoom.
 */
const LANDCOVER_COLORS: Readonly<Record<keyof NonNullable<Flavor['landcover']>, keyof MapPalette | null>> = {
  forest: 'park',
  grassland: 'park',
  scrub: 'park',
  farmland: 'land',
  barren: 'land',
  urban_area: 'land',
  glacier: 'land',
};

function landcoverFor(base: Flavor, palette: MapPalette): Flavor['landcover'] {
  if (base.landcover === undefined) return undefined;
  const landcover = { ...base.landcover };
  for (const [key, source] of Object.entries(LANDCOVER_COLORS) as [
    keyof typeof landcover,
    keyof MapPalette | null,
  ][]) {
    if (source !== null) landcover[key] = palette[source];
  }
  return landcover;
}

export function flavorFor(palette: MapPalette, mode: ThemeMode): Flavor {
  const base = namedFlavor(mode);
  const flavor: Flavor = { ...base };
  for (const [source, keys] of Object.entries(FLAVOR_COLORS) as [keyof MapPalette, readonly ColorKey[]][]) {
    for (const key of keys) flavor[key] = palette[source];
  }
  const landcover = landcoverFor(base, palette);
  return landcover === undefined ? flavor : { ...flavor, landcover };
}

function borderShadeLayer(palette: MapPalette): LayerSpecification {
  return {
    id: 'border-shade-fill',
    type: 'fill',
    source: BORDER_SHADE_SOURCE,
    paint: { 'fill-color': palette.mask, 'fill-opacity': BORDER_SHADE_OPACITY },
  };
}

function maskLayer(palette: MapPalette): LayerSpecification {
  return {
    id: 'dach-mask-fill',
    type: 'fill',
    source: MASK_SOURCE,
    paint: { 'fill-color': palette.mask, 'fill-opacity': 1 },
  };
}

/**
 * No sprite: MapLibre would read its pixels from a canvas, which fingerprinting protection blocks. Only the place
 * dots stay, as generated SDF images (place-dots.ts) in the label colors; one-way arrows are dropped and road numbers
 * keep their text without the shield.
 */
const ICON_ONLY_LAYERS: ReadonlySet<string> = new Set(['roads_oneway']);
const PLACE_DOT_LAYER = 'places_locality';

function withoutIcons(layer: LayerSpecification): LayerSpecification {
  if (layer.type !== 'symbol' || layer.layout === undefined) return layer;
  const layout = Object.fromEntries(Object.entries(layer.layout).filter(([key]) => !key.startsWith('icon-')));
  return { ...layer, layout };
}

function withPlaceDots(layer: LayerSpecification, palette: MapPalette): LayerSpecification {
  if (layer.type !== 'symbol') return layer;
  return {
    ...layer,
    paint: {
      ...layer.paint,
      'icon-color': palette.label,
      'icon-halo-color': palette.labelHalo,
      'icon-halo-width': 1,
    },
  };
}

function basemapLayers(inputs: BaseStyleInputs): LayerSpecification[] {
  if (inputs.tilesUrl === null) return [];
  const layers = protomapsLayers(BASEMAP_SOURCE, flavorFor(inputs.palette, inputs.mode), { lang: 'de' });
  return layers
    .filter((layer) => layer.id !== 'background' && !ICON_ONLY_LAYERS.has(layer.id))
    .map((layer) =>
      layer.id === PLACE_DOT_LAYER ? withPlaceDots(layer, inputs.palette) : withoutIcons(layer),
    );
}

function sourcesOf(inputs: BaseStyleInputs): StyleSpecification['sources'] {
  const sources: StyleSpecification['sources'] = { [MASK_SOURCE]: { type: 'geojson', data: inputs.maskUrl } };
  if (inputs.tilesUrl !== null) {
    sources[BORDER_SHADE_SOURCE] = { type: 'geojson', data: inputs.borderShadeUrl };
    sources[BASEMAP_SOURCE] = {
      type: 'vector',
      url: `pmtiles://${inputs.tilesUrl}`,
      attribution: inputs.attribution,
    };
  }
  return sources;
}

export function buildBaseStyle(inputs: BaseStyleInputs): StyleSpecification {
  const { palette } = inputs;
  const background: LayerSpecification = {
    id: 'background',
    type: 'background',
    paint: { 'background-color': palette.mask },
  };
  const basemap = basemapLayers(inputs);
  const sources = sourcesOf(inputs);
  return {
    version: 8,
    glyphs: inputs.glyphsUrl,
    sources,
    // Above the basemap the dimmed border zone, then the opaque mask outside of it (K-01).
    layers: [
      background,
      ...basemap,
      ...(basemap.length === 0 ? [] : [borderShadeLayer(palette)]),
      maskLayer(palette),
    ],
  };
}
