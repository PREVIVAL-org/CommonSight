/**
 * Generated place dots instead of a sprite: SDF images for "townspot" and "capital", nothing else.
 */
import { describe, expect, it } from 'vitest';
import { placeDotImage } from '../../src/map/place-dots';

describe('place dots', () => {
  it('creates square SDF images, inside opaque, outside transparent, the edge at 0.75', () => {
    const dot = placeDotImage('townspot');
    expect(dot).not.toBeNull();
    const { width, height, data } = dot!;
    expect([width, height]).toEqual([16, 16]);
    expect(data).toHaveLength(16 * 16 * 4);
    const alpha = (x: number, y: number): number => data[(y * width + x) * 4 + 3] ?? -1;
    expect(alpha(8, 8)).toBe(255);
    expect(alpha(0, 0)).toBe(0);
    // Pixel centre about 3.5 px from the centre lies just inside the edge of the radius-4 dot (0.75 = 191).
    expect(alpha(4, 7)).toBeGreaterThan(180);
    expect(alpha(4, 7)).toBeLessThan(215);
  });

  it('draws capitals larger and knows no other icons', () => {
    expect(placeDotImage('capital')!.width).toBeGreaterThan(placeDotImage('townspot')!.width);
    expect(placeDotImage('arrow')).toBeNull();
  });
});
