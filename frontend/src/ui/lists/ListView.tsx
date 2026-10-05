/**
 * Views "Messwerte" (measurements) and "Warnungen & Ereignisse" (warnings & events): selection, status line,
 * cards or table, load more, layer note (U-30 to U-36); below the section "Grenzgebiet" (border area) with items
 * beyond the border near the selection.
 */
import type { ListModel, ListViewId } from '../../state/selectors/list-selectors';
import { ItemCard } from '../cards/ItemCard';
import { useActions, useAppState, useTexts } from '../hooks';
import { EmptyState } from './EmptyState';
import { ItemTable } from './ItemTable';
import { ListControls } from './ListControls';
import { RegionLine } from './RegionLine';
import { StatusLine } from './StatusLine';

function Entries({ model, table }: { model: ListModel; table: boolean }) {
  if (model.empty !== null) return <EmptyState reason={model.empty} status={model.status} />;
  if (table && model.rows.length > 0) return <ItemTable rows={model.rows} layer={model.layer} />;
  return (
    <ul className="card-grid">
      {model.views.map((view) => (
        <li key={view.base.id}>
          <ItemCard view={view} variant="compact" />
        </li>
      ))}
    </ul>
  );
}

/** Items beyond the border near the selection; separate from the DACH items and not counted with them. */
function BorderSection({ model, table }: { model: ListModel; table: boolean }) {
  const t = useTexts();
  const actions = useActions();
  const place = useAppState((state, selectors) => selectors.placeName(state));
  const km = useAppState((state, selectors) => selectors.vicinityKm(state));
  if (model.border.length === 0) return null;
  return (
    <section className="border-section" aria-labelledby="border-section-title">
      <h3 id="border-section-title">{t.ui('list.border', { count: model.borderTotal })}</h3>
      <p className="muted">{t.ui('list.borderHint', { place, km })}</p>
      {table && model.borderRows.length > 0 ? (
        <ItemTable rows={model.borderRows} layer={model.layer} />
      ) : (
        <ul className="card-grid">
          {model.border.map((view) => (
            <li key={view.base.id}>
              <ItemCard view={view} variant="compact" />
            </li>
          ))}
        </ul>
      )}
      {model.borderRemaining > 0 ? (
        <button type="button" className="button" onClick={() => actions.showMoreBorder()}>
          {t.ui('list.more', { count: model.borderRemaining })}
        </button>
      ) : null}
    </section>
  );
}

export function ListView({ view }: { view: ListViewId }) {
  const t = useTexts();
  const actions = useActions();
  const model = useAppState((state, selectors) => selectors.list(state, view));
  const table = useAppState((state) => state.selection.tableMode) && view === 'measurements';
  return (
    <div className="list-view">
      {/* Heading of the view for screen readers; the entries are h3 below it. */}
      <h2 className="visually-hidden">{t.ui(`view.${view}`)}</h2>
      <ListControls view={view} model={model} />
      <StatusLine status={model.status} />
      <RegionLine model={model} />
      <Entries model={model} table={table} />
      {model.remaining > 0 ? (
        <button type="button" className="button" onClick={() => actions.showMore()}>
          {t.ui('list.more', { count: model.remaining })}
        </button>
      ) : null}
      {model.status.note === null || model.empty !== null ? null : (
        <p className="layer-note">{model.status.note}</p>
      )}
      {model.status.regionNote === null ? null : <p className="layer-note">{model.status.regionNote}</p>}
      <BorderSection model={model} table={table} />
    </div>
  );
}
