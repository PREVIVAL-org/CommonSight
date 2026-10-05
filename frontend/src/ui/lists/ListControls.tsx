/**
 * Layer selection, text filter "Ort oder Stichwort ..." (place or keyword) and cards/table toggle (U-30, U-33).
 */
import { useId } from 'react';
import type { LayerId } from '../../contract/types';
import type { ListModel, ListViewId } from '../../state/selectors/list-selectors';
import { useActions, useAppState, useTexts } from '../hooks';

function ViewModeSwitch() {
  const t = useTexts();
  const actions = useActions();
  const table = useAppState((state) => state.selection.tableMode);
  return (
    <div className="field">
      {t.ui('list.viewMode')}
      <div className="segmented" role="group" aria-label={t.ui('list.viewMode')}>
        <button type="button" aria-pressed={!table} onClick={() => actions.setTableMode(false)}>
          {t.ui('list.cards')}
        </button>
        <button type="button" aria-pressed={table} onClick={() => actions.setTableMode(true)}>
          {t.ui('list.table')}
        </button>
      </div>
    </div>
  );
}

function LayerSelect({ view, model }: { view: ListViewId; model: ListModel }) {
  const t = useTexts();
  const actions = useActions();
  const id = useId();
  return (
    <label className="field" htmlFor={id}>
      {t.ui('list.layerSelect')}
      <select
        id={id}
        className="select"
        value={model.layer}
        onChange={(event) => actions.setListLayer(view, event.target.value as LayerId)}
      >
        {model.options.map((option) => (
          <option key={option.layer} value={option.layer}>
            {option.name}
          </option>
        ))}
      </select>
    </label>
  );
}

export function ListControls({ view, model }: { view: ListViewId; model: ListModel }) {
  const t = useTexts();
  const actions = useActions();
  const query = useAppState((state) => state.selection.query);
  const filterId = useId();
  return (
    <div className="panel list-controls">
      <LayerSelect view={view} model={model} />
      <label className="field" htmlFor={filterId}>
        {t.ui('list.filter')}
        <input
          id={filterId}
          className="input"
          type="search"
          value={query}
          placeholder={t.ui('list.filterPlaceholder')}
          onChange={(event) => actions.setQuery(event.target.value)}
        />
      </label>
      {view === 'measurements' ? <ViewModeSwitch /> : null}
    </div>
  );
}
