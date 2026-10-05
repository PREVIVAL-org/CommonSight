// @vitest-environment jsdom
/**
 * Whole UI with a given store: page head with country selection, views, layers, map, news, key figures,
 * detail sheet.
 */
import { act, cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { App } from '../../src/ui/App';
import { NOW } from '../support/deps';
import { snapshot, statusAT } from '../support/fixtures';
import { renderWithServices } from '../support/render';
import { createTestStore, loadInto } from '../support/store';
import { LAYER_IDS, layerMeta } from '../../src/contract/master-data';
import { LAYER_LIST_IDS } from '../../src/domain/layer-slots';

afterEach(cleanup);

function loadedStore() {
  const bundle = createTestStore('AT');
  loadInto(bundle, statusAT(), [
    snapshot('warnings-AT'),
    snapshot('weather-AT'),
    snapshot('air-AT'),
    snapshot('radiation-AT'),
    snapshot('space-global'),
    snapshot('news-global'),
  ]);
  bundle.actions.syncStarted(NOW - 18_000);
  bundle.actions.setSyncing(false);
  return bundle;
}

/** Opens the country/region list with the keyboard, as Radix Select offers it. */
function openPicker(): HTMLElement {
  fireEvent.keyDown(screen.getByRole('combobox', { name: 'Land / Region' }), { key: 'Enter' });
  return screen.getByRole('listbox');
}

function choosePlace(name: string): void {
  fireEvent.keyDown(within(openPicker()).getByRole('option', { name }), { key: 'Enter' });
}

describe('App', () => {
  it('renders the header, the page head with the place picker and the overview without footer bar', () => {
    renderWithServices(<App />, loadedStore());
    // Without a name of the installation: CommonSight.
    expect(screen.getByRole('banner')).toContainElement(
      screen.getByRole('heading', { level: 1, name: 'CommonSight' }),
    );
    expect(screen.getByRole('combobox', { name: 'Land / Region' })).toHaveTextContent('Ganz Österreich');
    expect(screen.queryByText('Mein Lagebild')).not.toBeInTheDocument();
    expect(screen.queryByRole('contentinfo')).not.toBeInTheDocument();
    const footer = screen.getByText(/Version 0\.0\.0-test/);
    expect(footer).toHaveClass('app-version');
    expect(footer).toHaveTextContent('Powered by CommonSight Version 0.0.0-test');
    expect(screen.getByRole('link', { name: 'CommonSight' })).toHaveAttribute(
      'href',
      'https://github.com/PREVIVAL-org/CommonSight',
    );
    expect(screen.getByRole('tab', { name: /Lagekarte/, selected: true })).toBeInTheDocument();
    expect(screen.getByText(/^3 Warnungen in den verbundenen Quellen · unvollständig/)).toHaveTextContent(
      '3 Warnungen in den verbundenen Quellen · unvollständig · Anzeigen',
    );
    expect(screen.queryByRole('button', { name: /aktualisieren/i })).not.toBeInTheDocument();
  });

  it('switches the border zone with "Grenzgebiet" next to the country selection (ADR 0038)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    const toggle = screen.getByRole('switch', { name: 'Grenzgebiet' });
    expect(toggle.closest('.toolbar-row')).not.toBeNull();
    expect(toggle).toBeChecked();
    fireEvent.click(toggle);
    expect(bundle.store.getState().selection.borderZone).toBe(false);
  });

  it('shows source status and "Datenquellen" in the layer panel (U-22, U-40)', () => {
    renderWithServices(<App />, loadedStore());
    const layers = screen.getByRole('region', { name: 'Ebenen' });
    expect(within(layers).getByText('Quellenstatus:')).toBeInTheDocument();
    expect(within(layers).getByRole('button', { name: 'Datenquellen' })).toBeInTheDocument();
  });

  it('offers every country with its regions in one picker, with SVG flags (U-10, U-12)', () => {
    renderWithServices(<App />, loadedStore());
    const trigger = screen.getByRole('combobox', { name: 'Land / Region' });
    // The closed field shows flag and name, the flag as SVG instead of an emoji.
    expect(trigger).toHaveTextContent(/^Ganz Österreich$/);
    expect(trigger.querySelector('svg.country-flag')).not.toBeNull();
    const list = openPicker();
    const options = within(list).getAllByRole('option');
    const names = options.map((option) => option.textContent ?? '');
    expect(names[0]).toBe('Alle Länder');
    expect(names.filter((name) => name.startsWith('Ganz '))).toEqual([
      'Ganz Deutschland',
      'Ganz Österreich',
      'Ganz Schweiz',
    ]);
    expect(names.indexOf('Tessin')).toBeGreaterThan(names.indexOf('Ganz Schweiz'));
    // Flags before the countries, regions indented without a flag.
    const austria = within(list).getByRole('option', { name: 'Ganz Österreich' });
    expect(austria.querySelector('svg.country-flag')).not.toBeNull();
    const ticino = within(list).getByRole('option', { name: 'Tessin' });
    expect(ticino).toHaveAttribute('data-region', 'true');
    expect(ticino.querySelector('svg.country-flag')).toBeNull();
  });

  it('switches country and region with the picker (U-11, U-12)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    choosePlace('Wien');
    expect(bundle.store.getState().selection).toMatchObject({ country: 'AT', regionId: 'AT-9' });
    choosePlace('Tessin');
    expect(bundle.store.getState().selection).toMatchObject({ country: 'CH', regionId: 'CH-TI' });
    choosePlace('Ganz Schweiz');
    expect(bundle.store.getState().selection).toMatchObject({ country: 'CH', regionId: null });
    expect(screen.getByRole('combobox', { name: 'Land / Region' })).toHaveTextContent(/^Ganz Schweiz$/);
  });

  it('offers "Alle Länder" as first entry (ADR 0037)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    choosePlace('Wien');
    choosePlace('Alle Länder');
    expect(bundle.store.getState().selection).toMatchObject({ country: 'ALL', regionId: null });
    const trigger = screen.getByRole('combobox', { name: 'Land / Region' });
    expect(trigger).toHaveTextContent(/^Alle Länder$/);
    expect(trigger.querySelector('svg.country-flag')).toBeNull();
  });

  it('shows one line per layer with a status indicator instead of status text (U-20, U-22)', () => {
    renderWithServices(<App />, loadedStore());
    const layers = screen.getByRole('region', { name: 'Ebenen' });
    expect(within(layers).queryByText('Wird geladen')).not.toBeInTheDocument();
    expect(within(layers).queryByText(/nicht auf der Karte/)).not.toBeInTheDocument();
    expect(
      within(layers).getAllByRole('img', {
        name: /Quelle verbunden|Teilweise verfügbar|Daten werden abgerufen/,
      }).length,
    ).toBe(8);
  });

  it('toggles map layers and counts only those (U-20)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    const onMap = LAYER_LIST_IDS.filter((id) => layerMeta(id).onMap).length;
    expect(screen.getByText(`3 / ${onMap} aktiv`)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('switch', { name: /Strahlung/ }));
    expect(bundle.store.getState().selection.activeLayers).toContain('radiation');
    expect(screen.getByText(`4 / ${onMap} aktiv`)).toBeInTheDocument();
  });

  it('shows space weather last, without a switch, and opens its details (U-21)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    const other = screen.getByRole('list', { name: 'Weitere Lagedaten ohne Karte' });
    expect(within(other).getByText('Weltraumwetter')).toBeInTheDocument();
    expect(screen.queryByRole('switch', { name: /Weltraumwetter/ })).not.toBeInTheDocument();
    fireEvent.click(within(other).getByRole('button', { name: 'Weltraumwetter ansehen' }));
    expect(bundle.store.getState().ui.sheet).toEqual({ type: 'layer', layer: 'space' });
  });

  it('shows the number of active layers, the update countdown and the link "Legende" below the map (U-24, U-25, U-51)', () => {
    renderWithServices(<App />, loadedStore());
    const legend = screen.getByRole('group', { name: 'Kartenlegende' });
    expect(legend).toHaveTextContent(/^3 Ebenen aktivAktualisierung in 42 s42 sLegende$/);
    expect(within(legend).getByTitle(/automatisch jede Minute abgeglichen/)).toHaveAttribute(
      'data-state',
      'live',
    );
    expect(screen.queryByRole('button', { name: 'Messpunktfarben' })).not.toBeInTheDocument();
  });

  it('shows news with topic filter and metric tiles (U-26, U-27)', () => {
    renderWithServices(<App />, loadedStore());
    const news = screen.getByRole('region', { name: 'Nachrichtenlage' });
    expect(within(news).getAllByRole('article')).toHaveLength(3);
    const topic = within(news).getByRole('combobox', { name: 'Thema filtern' });
    expect(topic).toHaveValue('all');
    fireEvent.change(topic, { target: { value: 'conflict' } });
    expect(within(news).getAllByRole('article')).toHaveLength(1);
    expect(within(news).getByText(/einzelne Feeds fehlen/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Wetter.*Details öffnen$/ })).toHaveTextContent('18,5 °C');
    expect(screen.getByRole('button', { name: /^Weltraumwetter.*Details öffnen$/ })).toHaveTextContent(
      'Kp 5,33',
    );
  });

  it('explains the point colors via "Legende" below the map and in the water sheet (U-25)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    const legend = screen.getByRole('group', { name: 'Kartenlegende' });
    fireEvent.click(within(legend).getByRole('button', { name: 'Legende: Farben der Messpunkte' }));
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getAllByRole('heading', { name: 'Farben auf der Karte' })).toHaveLength(1);
    expect(within(dialog).getByText(/^Österreich: orange bei eHYD-Hochwasserstufe 1/)).toBeInTheDocument();
    expect(within(dialog).getByText('Ein grauer Punkt ist keine Entwarnung.')).toBeInTheDocument();
    act(() => bundle.actions.openSheet({ type: 'layer', layer: 'water' }));
    expect(within(screen.getByRole('dialog')).getByText(/^Österreich: orange bei eHYD/)).toBeInTheDocument();
    act(() => bundle.actions.closeSheet());
    fireEvent.click(screen.getByRole('switch', { name: /Wasserpegel/ }));
    expect(legend).toHaveTextContent(/^2 Ebenen aktivAktualisierung in 42 s42 s$/);
  });

  it('opens the source overview in the sheet and closes it again (U-40)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    fireEvent.click(screen.getByRole('button', { name: 'Datenquellen' }));
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByText('Datenquellen & Abdeckung')).toBeInTheDocument();
    expect(within(dialog).getAllByText(/^· letzter Abruf: \d\d\.\d\d\., \d\d:\d\d/).length).toBeGreaterThan(
      0,
    );
    expect(within(dialog).getByText('Österreich · Quellenstatus und Aktualität')).toBeInTheDocument();
    expect(within(dialog).getByText('Datennutzung')).toBeInTheDocument();
    expect(within(dialog).getAllByText('Daten ansehen')).toHaveLength(LAYER_IDS.length);
    fireEvent.click(within(dialog).getByRole('button', { name: 'Schließen' }));
    expect(bundle.store.getState().ui.sheet).toBeNull();
  });

  it('keeps the focus in the sheet when its content changes (T-09)', () => {
    renderWithServices(<App />, loadedStore());
    fireEvent.click(screen.getByRole('button', { name: 'Datenquellen' }));
    const [first] = within(screen.getByRole('dialog')).getAllByText('Daten ansehen');
    first?.focus();
    fireEvent.click(first as HTMLElement);
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByRole('heading', { level: 2 })).toHaveFocus();
  });

  it('returns the focus to the opening button when the sheet closes (T-09)', async () => {
    renderWithServices(<App />, loadedStore());
    const opener = screen.getByRole('button', { name: 'Datenquellen' });
    opener.focus();
    fireEvent.click(opener);
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    // Radix returns the focus only on the next tick.
    await waitFor(() => expect(opener).toHaveFocus());
  });

  it('opens an item sheet from a metric tile and offers all entries of the layer (U-42)', () => {
    renderWithServices(<App />, loadedStore());
    fireEvent.click(screen.getByRole('button', { name: /^Strahlung.*Details öffnen$/ }));
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByRole('heading', { name: 'Wien-Hohe Warte', level: 2 })).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Alle Einträge dieser Ebene' }));
    expect(within(screen.getByRole('dialog')).getByText('Strahlung', { selector: 'h2' })).toBeInTheDocument();
  });

  it('shows the offline banner (U-06)', () => {
    const bundle = loadedStore();
    renderWithServices(<App />, bundle);
    act(() => bundle.actions.setOnline(false));
    expect(
      screen.getByText('Keine Internetverbindung. Angezeigte Daten können veraltet sein.'),
    ).toBeInTheDocument();
  });

  it('closes the map notice and shows it again from the "i" in the toolbar row (U-23)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    fireEvent.click(screen.getByRole('button', { name: 'Kartenhinweis schließen' }));
    expect(bundle.store.getState().selection.mapNoticeHidden).toBe(true);
    const toolbar = screen.getByRole('combobox', { name: 'Land / Region' }).closest('.toolbar-row');
    if (!(toolbar instanceof HTMLElement)) throw new Error('toolbar row missing');
    fireEvent.click(within(toolbar).getByRole('button', { name: 'Kartenhinweis einblenden' }));
    expect(bundle.store.getState().selection.mapNoticeHidden).toBe(false);
    expect(
      within(toolbar).queryByRole('button', { name: 'Kartenhinweis einblenden' }),
    ).not.toBeInTheDocument();
  });

  it('opens the warnings from the map notice with "Anzeigen" (U-23)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    fireEvent.click(screen.getByRole('button', { name: 'Warnungen anzeigen' }));
    expect(bundle.store.getState().selection).toMatchObject({
      view: 'events',
      listLayers: { events: 'warnings' },
    });
  });

  it('switches to the events view and back to the map with "Auf Karte anzeigen" (K-09)', () => {
    const { bundle } = renderWithServices(<App />, loadedStore());
    fireEvent.click(screen.getByRole('button', { name: 'Warnmeldungen ansehen' }));
    expect(bundle.store.getState().selection.view).toBe('events');
    fireEvent.click(screen.getAllByRole('button', { name: 'Auf Karte anzeigen' })[0]!);
    expect(bundle.store.getState().selection.view).toBe('overview');
    expect(bundle.store.getState().map.focus?.type).toBe('item');
  });
});
