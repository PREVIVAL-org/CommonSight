/**
 * Tooltip from the view model: the same core details as the card, source content escaped (K-07, U-83).
 */
import { describe, expect, it } from 'vitest';
import type { WarningItem } from '../../src/contract/types';
import { toItemView } from '../../src/domain/views/item-view';
import { escapeHtml, tooltipHtml } from '../../src/map/tooltip';
import { snapshot } from '../support/fixtures';
import { viewDeps } from '../support/deps';

describe('tooltipHtml', () => {
  it('shows title, level, hazard, area, validity, source and time of a warning', () => {
    const data = snapshot('warnings-AT');
    const html = tooltipHtml(
      toItemView(data.items[0]!, { layer: 'warnings', source: data.source }, viewDeps()),
    );
    expect(html).toContain('Gewitterwarnung · Orange');
    expect(html).toContain('Warnstufe Orange · Gewitter');
    expect(html).toContain('Wien, Niederösterreich');
    expect(html).toContain('Gültig ab 28.09., 12:00 bis 29.09., 08:00');
    expect(html).toContain('GeoSphere Austria · 28.09., 11:30');
  });

  it('shows value, reference and assessment with basis of a measurement', () => {
    const data = snapshot('water-DE');
    const html = tooltipHtml(toItemView(data.items[0]!, { layer: 'water', source: data.source }, viewDeps()));
    expect(html).toContain('612 cm · über lokalem Pegelnullpunkt');
    expect(html).toContain('MHW erreicht / überschritten');
    expect(html).toContain('PEGELONLINE: mittlerer Hochwasserstand');
  });

  it('escapes all source content', () => {
    const data = snapshot('warnings-AT');
    const evil: WarningItem = {
      ...(data.items[0] as WarningItem),
      title: '<img src=x onerror=alert(1)>',
      area: '"&\'',
    };
    const html = tooltipHtml(toItemView(evil, { layer: 'warnings', source: '<b>' }, viewDeps()));
    expect(html).not.toContain('<img');
    expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
    expect(html).toContain('&quot;&amp;&#39;');
    expect(html).toContain('&lt;b&gt;');
    expect(escapeHtml('a<b')).toBe('a&lt;b');
  });
});
