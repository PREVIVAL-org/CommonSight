/**
 * View models of the panels: the frame of a tile around what a layer computes, status of a layer (U-16, U-27, U-31).
 */
import { describe, expect, it } from 'vitest';
import { regions } from '../../src/contract/master-data';
import type { Availability } from '../../src/domain/layer-availability';
import { scopeToRegion } from '../../src/domain/region-filter';
import { findRegion } from '../../src/domain/region-options';
import { toLayerStatusView } from '../../src/domain/views/layer-status-view';
import { toTileView } from '../../src/domain/views/metric-tile-view';
import { uiFormat } from '../../src/domain/views/ui-format';
import type { LayerTile, TileInput, TileValue } from '../../src/sdk/ui';
import { snapshot, statusAT } from '../support/fixtures';
import { viewDeps } from '../support/deps';

/** A tile part as a layer package brings it, with a fixed value. */
function part(value: TileValue | null): LayerTile {
  return { rank: 1, title: { key: 'layer.weather.name' }, value: () => value };
}

function tile(value: TileValue | null, availability: Availability = 'ok') {
  const deps = viewDeps();
  const input: TileInput = {
    snapshot: undefined,
    matched: [],
    reference: null,
    nowMs: deps.nowMs,
    format: uiFormat(deps),
  };
  return toTileView({ layer: 'weather', part: part(value) }, input, availability, deps);
}

describe('metric tiles', () => {
  it('show what the layer computed and open the item it names, otherwise the layer', () => {
    const value: TileValue = { value: '18,5 °C', detail: 'Wien', time: '28.09., 14:00' };
    expect(tile({ ...value, itemId: 'at-wien' })).toMatchObject({
      title: 'Wetter',
      status: 'ready',
      value: '18,5 °C',
      hint: null,
      highlight: false,
      history: null,
      target: { type: 'item', layer: 'weather', itemId: 'at-wien' },
    });
    expect(tile(value).target).toEqual({ type: 'layer', layer: 'weather' });
  });

  it('translate hint and course label of the layer', () => {
    const shown = tile({
      value: 'x',
      detail: 'y',
      time: 'z',
      hint: { key: 'layer.radiation.tile.olderValue' },
      history: { values: [1, 2], max: 9, label: { key: 'layer.space.tile.history' } },
    });
    expect(shown.hint).toBe('älterer Wert');
    expect(shown.history).toEqual({ values: [1, 2], max: 9, label: 'Kp-Verlauf der letzten Messintervalle' });
  });

  it('show loading or no data without a value and open the layer', () => {
    expect(tile(null, 'loading')).toMatchObject({
      status: 'loading',
      detail: 'Wird geladen ...',
      target: { type: 'layer', layer: 'weather' },
    });
    expect(tile(null, 'error')).toMatchObject({ status: 'empty', detail: 'Keine Daten' });
  });
});

describe('layer status view', () => {
  const status = statusAT();
  const warnings = snapshot('warnings-AT');

  it('shows status, source, count, dates, note, issues and region note', () => {
    const view = toLayerStatusView(
      {
        layer: 'warnings',
        status: status.layers.warnings,
        snapshot: warnings,
        availability: 'partial',
        scoped: scopeToRegion(warnings.items, 'AT-9', true),
        region: findRegion(regions, 'AT-9'),
      },
      viewDeps(),
    );
    expect(view).toMatchObject({
      name: 'Amtliche Warnungen',
      availabilityText: 'Teilweise verfügbar',
      source: 'GeoSphere Austria',
      count: 1,
      countText: '1 Eintrag',
      fetchedAt: '28.09., 13:59',
      fetchedAge: 'vor 1 Minute',
      lastFetch: 'letzter Abruf: 28.09., 13:59',
      sourceDate: '28.09., 13:55',
      regionNote: 'Region Wien: 1 zugeordnete Einträge, 1 ohne sichere Regionalzuordnung.',
      lastError: null,
      stale: false,
    });
    expect(view.issues).toEqual([
      'Bei 1 Meldung(en) fehlt der Detailtext; Warnart, Stufe und Fläche bleiben sichtbar.',
    ]);
    expect(view.note).toContain('GeoSphere Austria');
  });

  it('reports a failed update with the last good state (D-05)', () => {
    const view = toLayerStatusView(
      {
        layer: 'radiation',
        status: status.layers.radiation,
        snapshot: undefined,
        availability: 'ok',
        scoped: scopeToRegion([], null, true),
        region: null,
      },
      viewDeps(),
    );
    expect(view.lastError).toBe(
      'Letzte Aktualisierung fehlgeschlagen (28.09., 13:58). Angezeigt wird der letzte gute Stand. Keine Quelle der Ebene war erreichbar.',
    );
    expect(view.source).toBeNull();
    expect(view.lastFetch).toMatch(/^letzter Abruf: .+, fehlgeschlagen$/);
  });

  it('marks a stale last fetch compactly (U-41)', () => {
    const entry = status.layers.weather;
    const view = toLayerStatusView(
      {
        layer: 'weather',
        status: entry === undefined ? undefined : { ...entry, stale: true, lastError: null },
        snapshot: undefined,
        availability: 'ok',
        scoped: scopeToRegion([], null, true),
        region: null,
      },
      viewDeps(),
    );
    expect(view.lastFetch).toBe('letzter Abruf: 28.09., 13:55, veraltet');
  });
});
