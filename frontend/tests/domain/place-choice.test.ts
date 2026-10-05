/**
 * Encoding of the combined country/region selection (U-10 to U-12).
 */
import { describe, expect, it } from 'vitest';
import { decodePlace, encodePlace } from '../../src/domain/place-choice';

describe('place choice', () => {
  it('encodes a whole country and a region', () => {
    expect(encodePlace({ country: 'AT', regionId: null })).toBe('AT');
    expect(encodePlace({ country: 'AT', regionId: 'AT-9' })).toBe('AT:AT-9');
  });

  it('decodes what it encodes', () => {
    expect(decodePlace('CH')).toEqual({ country: 'CH', regionId: null });
    expect(decodePlace('DE:DE-BY')).toEqual({ country: 'DE', regionId: 'DE-BY' });
  });

  it('rejects unknown countries and regions of another country', () => {
    expect(decodePlace('FR')).toBeNull();
    expect(decodePlace('AT:DE-BY')).toBeNull();
    expect(decodePlace('')).toBeNull();
  });
});
