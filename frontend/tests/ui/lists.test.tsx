// @vitest-environment jsdom
/**
 * List view: status line, empty states with cause, table, load more and notes (U-30 to U-36, U-54).
 */
import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { ListView } from '../../src/ui/lists/ListView';
import { snapshot, statusAT } from '../support/fixtures';
import { renderWithServices } from '../support/render';
import { createTestStore, loadInto } from '../support/store';

afterEach(cleanup);

function loadedStore() {
  const bundle = createTestStore('AT');
  loadInto(bundle, statusAT(), [
    snapshot('warnings-AT'),
    snapshot('weather-AT'),
    snapshot('radiation-AT'),
    snapshot('nature-AT'),
  ]);
  return bundle;
}

describe('ListView', () => {
  it('shows the section "Grenzgebiet" below the DACH items', () => {
    const bundle = createTestStore('AT');
    loadInto(bundle, statusAT(), [snapshot('weather-AT'), snapshot('weather-border')]);
    renderWithServices(<ListView view="measurements" />, bundle);
    const section = screen.getByRole('region', { name: 'Grenzgebiet: 1 Eintrag' });
    expect(section).toHaveTextContent('Einträge jenseits der Grenze bis 200 km von Österreich');
    expect(section).toHaveTextContent('Bolzano/Bozen (Italien)');
  });

  it('shows status line, cards and the layer note', () => {
    renderWithServices(<ListView view="events" />, loadedStore());
    expect(screen.getByText('Teilweise verfügbar')).toBeInTheDocument();
    expect(screen.getByText('3 Einträge')).toBeInTheDocument();
    expect(screen.getByText(/Bei 1 Meldung\(en\) fehlt der Detailtext/)).toBeInTheDocument();
    expect(screen.getAllByRole('article')).toHaveLength(3);
    expect(screen.getByText(/Die Wetterwarnungen gelten für den Dauersiedlungsraum/)).toBeInTheDocument();
  });

  it('filters by text and shows "Keine passenden Einträge"', () => {
    renderWithServices(<ListView view="events" />, loadedStore());
    fireEvent.change(screen.getByRole('searchbox', { name: /Einträge filtern/ }), {
      target: { value: 'nicht vorhanden' },
    });
    expect(screen.getByText('Keine passenden Einträge')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Originalquelle öffnen/ })).toHaveAttribute(
      'href',
      'https://warnungen.zamg.at/',
    );
  });

  it('shows "Daten werden abgerufen ..." for pending layers and "Datenzugang noch nicht eingerichtet" for setup', () => {
    const bundle = loadedStore();
    bundle.actions.setListLayer('measurements', 'water');
    const { unmount } = renderWithServices(<ListView view="measurements" />, bundle);
    expect(screen.getAllByText('Daten werden abgerufen ...').length).toBeGreaterThan(0);
    unmount();
    const swiss = createTestStore('CH');
    loadInto(swiss, null, [snapshot('traffic-CH')]);
    swiss.actions.setListLayer('events', 'traffic');
    renderWithServices(<ListView view="events" />, swiss);
    expect(screen.getByText('Datenzugang noch nicht eingerichtet')).toBeInTheDocument();
    expect(screen.getByText(/ASTRA · opentransportdata.swiss/, { selector: 'p' })).toBeInTheDocument();
  });

  it('shows the failure of a layer without hiding the last data (U-54)', () => {
    const bundle = loadedStore();
    bundle.actions.snapshotFailed('AT/warnings');
    renderWithServices(<ListView view="events" />, bundle);
    expect(screen.getByText('Nicht verfügbar')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Die Quelle ist derzeit nicht erreichbar. Das ist keine Entwarnung.',
    );
    expect(screen.getAllByRole('article')).toHaveLength(3);
  });

  it('switches to the table and reports the failed last update (U-33, D-05)', () => {
    const bundle = loadedStore();
    bundle.actions.setListLayer('measurements', 'radiation');
    renderWithServices(<ListView view="measurements" />, bundle);
    fireEvent.click(screen.getByRole('button', { name: 'Tabelle' }));
    expect(screen.getByRole('table')).toBeInTheDocument();
    expect(screen.getAllByRole('row')).toHaveLength(3);
    expect(screen.getByText(/Letzte Aktualisierung fehlgeschlagen/)).toBeInTheDocument();
  });

  it('shows the region line with access to unassigned entries (U-15, U-16)', () => {
    const bundle = loadedStore();
    bundle.actions.setRegion('AT-9');
    renderWithServices(<ListView view="events" />, bundle);
    expect(screen.getByText(/Wien · 1 regional zugeordneter Eintrag/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Ohne Ortszuordnung (1)' }));
    expect(bundle.store.getState().ui.sheet).toEqual({ type: 'unassigned' });
  });
});
