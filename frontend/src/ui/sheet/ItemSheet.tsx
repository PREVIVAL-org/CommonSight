/**
 * Detail sheet of an item: full card, note for warnings without text, original source, layer note,
 * "Alle Einträge dieser Ebene" (all items of this layer) (U-42).
 */
import type { LayerId } from '../../contract/types';
import { ItemCard } from '../cards/ItemCard';
import { useActions, useAppState, useTexts } from '../hooks';
import { ExternalLink } from '../parts/ExternalLink';

export function ItemSheet({ layer, itemId }: { layer: LayerId; itemId: string }) {
  const t = useTexts();
  const actions = useActions();
  const model = useAppState((state, selectors) => selectors.itemSheet(state, layer, itemId));
  const { view, status } = model;
  return (
    <>
      {view === null ? (
        <p className="notice" data-tone="info">
          {t.ui('sheet.item.missing')}
        </p>
      ) : (
        <>
          <ItemCard view={view} variant="full" />
          {view.kind === 'warning' && view.sections.length === 0 ? (
            <p className="notice">{t.ui('sheet.item.noText')}</p>
          ) : null}
          <ExternalLink href={view.base.url}>{t.ui('sheet.item.openFull')}</ExternalLink>
        </>
      )}
      {status.note === null ? null : <p className="layer-note">{status.note}</p>}
      <button type="button" className="button" onClick={() => actions.openSheet({ type: 'layer', layer })}>
        {t.ui('sheet.item.allEntries')}
      </button>
    </>
  );
}
