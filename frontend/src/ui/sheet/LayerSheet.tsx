/**
 * Detail sheet of a layer: note, status, fetch time, official source, the legend the layer contributes (meaning of
 * the point colors, U-25), further official information, items in full (U-41).
 */
import type { LayerId } from '../../contract/types';
import { layerUiParts } from '../../generated/layer-ui';
import type { LayerSheetModel } from '../../state/selectors/sheet-selectors';
import { ItemCard } from '../cards/ItemCard';
import { StatusNotices } from '../lists/StatusLine';
import { useAppState, useTexts } from '../hooks';
import { AvailabilityBadge } from '../parts/AvailabilityBadge';
import { ColorLegend } from '../parts/ColorLegend';
import { ExternalLink } from '../parts/ExternalLink';
import { StatusMeta } from '../parts/StatusMeta';
import { LayerNote } from '../parts/LayerNote';

function LayerFacts({ model }: { model: LayerSheetModel }) {
  const t = useTexts();
  const { status } = model;
  return (
    <section className="sheet-section">
      <div className="source-row-head">
        <AvailabilityBadge availability={status.availability} />
        <StatusMeta status={status} withSource={false} />
      </div>
      <LayerNote note={status.note} coverage={status.coverage} />
      {status.regionNote === null ? null : <p className="layer-note">{status.regionNote}</p>}
      <StatusNotices status={status} />
      {status.sourceUrl === null ? null : (
        <ExternalLink href={status.sourceUrl}>{t.ui('sheet.layer.openOfficial')}</ExternalLink>
      )}
    </section>
  );
}

function OfficialLinks({ model }: { model: LayerSheetModel }) {
  const t = useTexts();
  if (model.links === null) return null;
  return (
    <section className="sheet-section">
      <h3>{t.ui('sheet.layer.moreOfficial')}</h3>
      <ul className="link-list">
        {model.links.map((link) => (
          <li key={link.url}>
            <ExternalLink href={link.url}>{link.label}</ExternalLink>
          </li>
        ))}
      </ul>
    </section>
  );
}

function Entries({ model, layer }: { model: LayerSheetModel; layer: LayerId }) {
  const t = useTexts();
  const emptyText = layerUiParts[layer]?.emptyText;
  if (model.views.length === 0) {
    return (
      <p className="notice" data-tone="info">
        {emptyText === undefined ? t.ui('sheet.layer.empty') : t.msg(emptyText)}
      </p>
    );
  }
  return (
    <>
      <ul className="stack">
        {model.views.map((view) => (
          <li key={view.base.id}>
            <ItemCard view={view} variant="full" />
          </li>
        ))}
      </ul>
      {model.truncated ? <p className="muted">{t.ui('sheet.layer.limit')}</p> : null}
    </>
  );
}

export function LayerSheet({ layer }: { layer: LayerId }) {
  const model = useAppState((state, selectors) => selectors.layerSheet(state, layer));
  const legend = useAppState((state, selectors) => selectors.legendEntry(state, layer));
  return (
    <>
      <LayerFacts model={model} />
      {legend === null ? null : <ColorLegend entries={[legend]} />}
      <OfficialLinks model={model} />
      <Entries model={model} layer={layer} />
    </>
  );
}
