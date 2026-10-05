/**
 * Map credits: base map only (K-13); the credits of the sources and region borders are in the side bar.
 */
import { describe, expect, it } from 'vitest';
import { attributionHtml } from '../../src/map/attribution';

describe('map credits', () => {
  it('escape text and link, because MapLibre inserts them as HTML', () => {
    expect(attributionHtml({ text: `Hub'Eau <b>`, url: 'https://example.org/?a=1&b="2"' })).toBe(
      '<a href="https://example.org/?a=1&amp;b=&quot;2&quot;" target="_blank" rel="noopener">Hub&#39;Eau &lt;b&gt;</a>',
    );
  });
});
