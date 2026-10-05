/**
 * Line for the selected region: number of assigned items and access to "Ohne Ortszuordnung (N)" (unassigned)
 * (U-15, U-16).
 */
import type { ListModel } from '../../state/selectors/list-selectors';
import { useActions, useAppState, useTexts } from '../hooks';

export function RegionLine({ model }: { model: ListModel }) {
  const t = useTexts();
  const actions = useActions();
  const region = useAppState((state, selectors) => selectors.regionName(state));
  if (model.overregional) return <p className="muted">{t.ui('list.overregional')}</p>;
  if (region === null) return null;
  return (
    <p className="region-summary">
      {t.ui('region.matched', { region, count: model.status.count })}
      {model.unassigned > 0 ? (
        <>
          {' · '}
          <button
            type="button"
            className="button button-link"
            onClick={() => actions.openSheet({ type: 'unassigned' })}
          >
            {t.ui('sheet.unassigned.link', { count: model.unassigned })}
          </button>
        </>
      ) : null}
    </p>
  );
}
