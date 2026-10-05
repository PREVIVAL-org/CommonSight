/**
 * Detail sheet "Ohne Ortszuordnung" (unassigned): explanation and items per layer whose region cannot be
 * determined (U-15, U-43).
 */
import { ItemCard } from '../cards/ItemCard';
import { useAppState, useTexts } from '../hooks';

export function UnassignedSheet() {
  const t = useTexts();
  const model = useAppState((state, selectors) => selectors.unassigned(state));
  return (
    <>
      <p>{t.ui('sheet.unassigned.explain', { region: model.regionName })}</p>
      {model.total === 0 ? <p className="muted">{t.ui('sheet.unassigned.empty')}</p> : null}
      {model.groups.map((group) => (
        <section key={group.layer} className="sheet-section">
          <h3>{group.name}</h3>
          <ul className="stack">
            {group.views.map((view) => (
              <li key={view.base.id}>
                <ItemCard view={view} variant="compact" />
              </li>
            ))}
          </ul>
        </section>
      ))}
      <p className="muted">{t.ui('sheet.unassigned.check')}</p>
    </>
  );
}
