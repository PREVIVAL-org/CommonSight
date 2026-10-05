/**
 * Region and text filter, news, empty states, availability (U-14 to U-16, U-26, U-30, U-35, U-54).
 */
import { describe, expect, it } from 'vitest';
import { emptyReason } from '../../src/domain/empty-state';
import { isConnected, layerAvailability } from '../../src/domain/layer-availability';
import { catalog } from '../../src/contract/catalog';
import { feedOrder, newsFeed } from '../../src/domain/news-feed';
import { scopeToRegion } from '../../src/domain/region-filter';
import { summarizeSources } from '../../src/domain/source-summary';
import { filterByText, searchableText } from '../../src/domain/text-filter';
import { snapshot, statusAT } from '../support/fixtures';

const warnings = snapshot('warnings-AT');

describe('scopeToRegion', () => {
  it('without region keeps all items and collects nothing as unassigned', () => {
    const scoped = scopeToRegion(warnings.items, null, true);
    expect(scoped.matched).toHaveLength(3);
    expect(scoped.unassigned).toEqual([]);
    expect(scoped.filtered).toBe(false);
  });

  it('with region matches by regionIds and collects items without assignment separately', () => {
    const scoped = scopeToRegion(warnings.items, 'AT-9', true);
    expect(scoped.matched.map((item) => item.id)).toEqual([warnings.items[0]?.id]);
    expect(scoped.unassigned.map((item) => item.regionMatch)).toEqual(['none']);
  });

  it('never filters overregional layers', () => {
    expect(scopeToRegion(warnings.items, 'AT-9', false).matched).toHaveLength(3);
  });
});

describe('filterByText', () => {
  it('searches title and descriptive fields, ignoring case and accents', () => {
    expect(filterByText(warnings.items, 'HAGEL').map((item) => item.title)).toEqual([
      'Gewitterwarnung · Orange',
    ]);
    expect(filterByText(warnings.items, 'niederosterreich')).toHaveLength(1);
    expect(filterByText(warnings.items, 'tirol wind')).toHaveLength(1);
    expect(filterByText(warnings.items, '  ')).toHaveLength(3);
  });

  it('includes road and description of traffic notices', () => {
    const [notice] = snapshot('traffic-AT').items;
    expect(notice && searchableText(notice)).toContain('Pressbaum');
  });
});

describe('newsFeed', () => {
  const news = snapshot('news-global').items;

  it('sorts newest first and prefers the country feed on equal time (Q-NE-02)', () => {
    expect(newsFeed(news, 'all', ['orf', 'tagesschau', 'srf']).map((item) => item.feed)).toEqual([
      'orf',
      'tagesschau',
      'orf',
    ]);
    expect(newsFeed(news, 'all', ['tagesschau', 'orf', 'srf'])[0]?.feed).toBe('tagesschau');
  });

  it('filters by topic', () => {
    expect(newsFeed(news, 'conflict', []).map((item) => item.category)).toEqual(['conflict']);
  });
});

describe('feedOrder', () => {
  it('puts the feeds of the country first, then the others in catalog order, as the former newsOrder', () => {
    expect(feedOrder(catalog.newsFeeds, 'DE')).toEqual(['tagesschau', 'orf', 'srf']);
    expect(feedOrder(catalog.newsFeeds, 'AT')).toEqual(['orf', 'tagesschau', 'srf']);
    expect(feedOrder(catalog.newsFeeds, 'CH')).toEqual(['srf', 'tagesschau', 'orf']);
  });

  it('keeps several feeds of one country together and in order', () => {
    const feeds = [
      { id: 'a', country: 'DE' },
      { id: 'b', country: 'AT' },
      { id: 'c', country: 'DE' },
    ];
    expect(feedOrder(feeds, 'DE')).toEqual(['a', 'c', 'b']);
  });
});

describe('layerAvailability', () => {
  const status = statusAT();

  it('reports loading without status and snapshot', () => {
    expect(layerAvailability({ status: undefined, snapshot: undefined, loadFailed: false })).toBe('loading');
  });

  it('reports pending when the server has no snapshot yet (A-04)', () => {
    expect(layerAvailability({ status: status.layers.water, snapshot: undefined, loadFailed: false })).toBe(
      'pending',
    );
  });

  it('takes the server status for layers whose snapshot is not loaded (inactive layers, U-50)', () => {
    expect(
      layerAvailability({ status: status.layers.warnings, snapshot: undefined, loadFailed: false }),
    ).toBe('partial');
  });

  it('reports the status of loaded data and errors after a failed load (U-54)', () => {
    expect(layerAvailability({ status: status.layers.warnings, snapshot: warnings, loadFailed: false })).toBe(
      'partial',
    );
    expect(layerAvailability({ status: status.layers.warnings, snapshot: warnings, loadFailed: true })).toBe(
      'error',
    );
    expect(
      layerAvailability({ status: undefined, snapshot: snapshot('traffic-CH'), loadFailed: false }),
    ).toBe('setup');
  });

  it('counts connected and unreachable sources (U-22)', () => {
    expect(isConnected('partial')).toBe(true);
    expect(summarizeSources(['ok', 'partial', 'error', 'setup', 'loading'])).toEqual({
      connected: 2,
      unreachable: 1,
      total: 5,
    });
  });
});

describe('emptyReason', () => {
  it('names the cause of an empty list (U-35)', () => {
    expect(emptyReason('ok', 3, 1)).toBeNull();
    expect(emptyReason('setup', 0, 0)).toBe('setup');
    expect(emptyReason('pending', 0, 0)).toBe('pending');
    expect(emptyReason('loading', 0, 0)).toBe('loading');
    expect(emptyReason('error', 0, 0)).toBe('error');
    expect(emptyReason('ok', 4, 0)).toBe('noMatch');
    expect(emptyReason('ok', 0, 0)).toBe('none');
  });
});
