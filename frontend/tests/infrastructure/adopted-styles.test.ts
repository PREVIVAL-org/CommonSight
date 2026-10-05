/**
 * Stylesheets of the shadow root: moving the element in the page (disconnect, connect) does not pile them up.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { adoptStyles } from '../../src/infrastructure/adopted-styles';

class FakeSheet {
  css = '';
  replaceSync(css: string): void {
    this.css = css;
  }
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('adopted stylesheets', () => {
  it('replace the sheets of their group, keep the others in place', () => {
    vi.stubGlobal('CSSStyleSheet', FakeSheet);
    const shadow = { adoptedStyleSheets: [] as FakeSheet[] } as unknown as ShadowRoot;
    const css = () => (shadow.adoptedStyleSheets as unknown as FakeSheet[]).map((sheet) => sheet.css);

    adoptStyles(shadow, 'element', ['tokens', 'layout'], 'last');
    adoptStyles(shadow, 'map', ['maplibre'], 'first');
    expect(css()).toEqual(['maplibre', 'tokens', 'layout']);

    adoptStyles(shadow, 'element', ['tokens', 'layout'], 'last');
    adoptStyles(shadow, 'map', ['maplibre'], 'first');
    expect(css()).toEqual(['maplibre', 'tokens', 'layout']);
  });
});
