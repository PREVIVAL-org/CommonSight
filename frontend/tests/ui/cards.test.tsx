// @vitest-environment jsdom
/**
 * Cards per kind in both variants with a view model as input (U-80 to U-82, Architecture 12.4).
 */
import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import type { Snapshot } from '../../src/contract/types';
import type { ItemView } from '../../src/domain/views/item-view';
import { toItemView } from '../../src/domain/views/item-view';
import { ItemCard } from '../../src/ui/cards/ItemCard';
import { snapshot } from '../support/fixtures';
import { viewDeps } from '../support/deps';
import { renderWithServices } from '../support/render';

afterEach(cleanup);

function firstView(data: Snapshot, index = 0): ItemView {
  const item = data.items[index];
  if (item === undefined) throw new Error('fixture without item');
  return toItemView(item, { layer: data.layer, source: data.source }, viewDeps());
}

describe('ItemCard', () => {
  it('warning: compact shows level, hazard, area, validity and the shortened first section', () => {
    renderWithServices(<ItemCard view={firstView(snapshot('warnings-AT'))} variant="compact" />);
    expect(screen.getByText('Warnstufe Orange')).toBeInTheDocument();
    expect(screen.getByText('Gewitter')).toBeInTheDocument();
    expect(screen.getByText('Wien, Niederösterreich')).toBeInTheDocument();
    expect(screen.getByText('Gültig ab 28.09., 12:00 bis 29.09., 08:00')).toBeInTheDocument();
    expect(screen.queryByText('Empfehlungen')).not.toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: 'Details öffnen: Gewitterwarnung · Orange' }),
    ).toBeInTheDocument();
  });

  it('warning: full shows all sections', () => {
    renderWithServices(<ItemCard view={firstView(snapshot('warnings-AT'))} variant="full" />);
    expect(screen.getByRole('heading', { name: 'Empfehlungen' })).toBeInTheDocument();
    expect(screen.getByText(/Gegenstände im Freien sichern/)).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Gewitterwarnung · Orange' })).toBeInTheDocument();
  });

  it('measurement: compact shows value and label, full adds basis, origin and facts', () => {
    const view = firstView(snapshot('water-DE'), 1);
    const { unmount } = renderWithServices(<ItemCard view={view} variant="compact" />);
    expect(screen.getByText('845,5 cm')).toBeInTheDocument();
    expect(screen.getByText('Datenstand veraltet')).toBeInTheDocument();
    expect(screen.queryByText(/höchster Schifffahrtswasserstand/)).not.toBeInTheDocument();
    unmount();
    renderWithServices(<ItemCard view={view} variant="full" />);
    expect(screen.getByText(/höchster Schifffahrtswasserstand/)).toBeInTheDocument();
    expect(screen.getByText('Einstufung der Quelle')).toBeInTheDocument();
    expect(screen.getByText('1.234,5 m³/s')).toBeInTheDocument();
  });

  it('model value, earthquake, traffic, index and news show their fields', () => {
    renderWithServices(
      <>
        <ItemCard view={firstView(snapshot('weather-AT'))} variant="compact" />
        <ItemCard view={firstView(snapshot('nature-AT'))} variant="compact" />
        <ItemCard view={firstView(snapshot('traffic-AT'))} variant="full" />
        <ItemCard view={firstView(snapshot('space-global'))} variant="full" />
        <ItemCard view={firstView(snapshot('news-global'))} variant="compact" />
      </>,
    );
    expect(screen.getByText('18,5 °C')).toBeInTheDocument();
    expect(screen.getByText('Modellwert, keine Messung')).toBeInTheDocument();
    expect(screen.getByText('M 3,2')).toBeInTheDocument();
    expect(screen.getByText('A1 Westautobahn')).toBeInTheDocument();
    expect(screen.getByText('Originaltext (en)')).toBeInTheDocument();
    expect(screen.getByText('Kp 5,33')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Verlauf' })).toBeInTheDocument();
    expect(screen.getByText('tagesschau.de')).toBeInTheDocument();
  });

  it('every card has the source link and "Auf Karte anzeigen" only with a location (U-81)', () => {
    const { bundle } = renderWithServices(
      <>
        <ItemCard view={firstView(snapshot('nature-AT'))} variant="compact" />
        <ItemCard view={firstView(snapshot('news-global'))} variant="compact" />
      </>,
    );
    expect(screen.getAllByRole('link', { name: /Originalquelle öffnen/ })).toHaveLength(2);
    const showOnMap = screen.getAllByRole('button', { name: 'Auf Karte anzeigen' });
    expect(showOnMap).toHaveLength(1);
    fireEvent.click(showOnMap[0]!);
    expect(bundle.store.getState().map.focus).toMatchObject({
      type: 'item',
      layer: 'nature',
      itemId: 'us7000abcd',
    });
    expect(document.querySelectorAll('[part="card"]')).toHaveLength(2);
  });
});
