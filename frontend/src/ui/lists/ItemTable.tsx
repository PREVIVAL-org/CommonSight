/**
 * Table view of the measurements: place/station, value with assessment, data timestamp, source as link,
 * "Auf Karte anzeigen" (show on map) (U-33).
 */
import type { LayerId } from '../../contract/types';
import type { TableRowView } from '../../domain/views/table-row-view';
import { LevelBadge } from '../cards/value-parts';
import { useActions, useTexts } from '../hooks';
import { ExternalLink } from '../parts/ExternalLink';

interface RowProps {
  row: TableRowView;
  layer: LayerId;
}

function PlaceCell({ row, layer }: RowProps) {
  const actions = useActions();
  const open = (): void => actions.openSheet({ type: 'item', layer, itemId: row.id });
  return (
    <th scope="row">
      <button type="button" className="button button-link" onClick={open}>
        {row.place}
      </button>
    </th>
  );
}

function MapCell({ row, layer }: RowProps) {
  const t = useTexts();
  const actions = useActions();
  if (!row.hasLocation) return <td />;
  return (
    <td>
      <button type="button" className="button button-link" onClick={() => actions.showOnMap(layer, row.id)}>
        {t.ui('card.showOnMap')}
      </button>
    </td>
  );
}

function Row({ row, layer }: RowProps) {
  return (
    <tr>
      <PlaceCell row={row} layer={layer} />
      <td>
        {row.value} {row.badge === null ? null : <LevelBadge badge={row.badge} />}
      </td>
      <td>{row.time}</td>
      <td>
        <ExternalLink href={row.url}>{row.source}</ExternalLink>
      </td>
      <MapCell row={row} layer={layer} />
    </tr>
  );
}

export function ItemTable({ rows, layer }: { rows: TableRowView[]; layer: LayerId }) {
  const t = useTexts();
  return (
    <div className="table-wrap">
      <table className="table">
        <caption className="visually-hidden">{t.ui('table.caption')}</caption>
        <thead>
          <tr>
            <th scope="col">{t.ui('table.place')}</th>
            <th scope="col">{t.ui('table.value')}</th>
            <th scope="col">{t.ui('table.time')}</th>
            <th scope="col">{t.ui('table.source')}</th>
            <th scope="col">{t.ui('table.map')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <Row key={row.id} row={row} layer={layer} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
