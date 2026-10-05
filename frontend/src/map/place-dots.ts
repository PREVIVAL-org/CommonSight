/**
 * Place dots next to the city names ("townspot", capitals larger), generated as signed distance field images
 * instead of a sprite. MapLibre then reads no pixels from a canvas, which browsers with fingerprinting protection
 * block and report as a warning; color and halo come from the style (icon-color, icon-halo-color).
 */

/** Raw RGBA pixels as accepted by `map.addImage` without decoding through a canvas. */
export interface DotImage {
  width: number;
  height: number;
  data: Uint8Array;
}

/** Edge value and falloff of MapLibre's SDF encoding: 0.75 at the edge, 8 px from fully inside to outside. */
const SDF_EDGE = 0.75;
const SDF_RADIUS_PX = 8;

/** Radius of the dot in pixels; padding leaves room for the halo. */
const DOTS: Readonly<Record<string, number>> = { townspot: 4, capital: 5.5 };
const PADDING_PX = 4;

function dotImage(radius: number): DotImage {
  const size = Math.ceil(2 * (radius + PADDING_PX));
  const center = size / 2;
  const data = new Uint8Array(size * size * 4);
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const distance = Math.hypot(x + 0.5 - center, y + 0.5 - center) - radius;
      const value = Math.round(255 * Math.min(1, Math.max(0, SDF_EDGE - distance / SDF_RADIUS_PX)));
      data.set([255, 255, 255, value], (y * size + x) * 4);
    }
  }
  return { width: size, height: size, data };
}

/** The image for an icon name of the basemap, or `null` if it is not a place dot. */
export function placeDotImage(name: string): DotImage | null {
  const radius = DOTS[name];
  return radius === undefined ? null : dotImage(radius);
}
