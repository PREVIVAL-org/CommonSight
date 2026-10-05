/**
 * Empty state of a list with cause, layer note and "Originalquelle öffnen" (open original source) (U-35).
 */
import type { EmptyReason } from '../../domain/empty-state';
import type { UiTextKey } from '../../i18n/ui-texts.de';
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import { useTexts } from '../hooks';
import { ExternalLink } from '../parts/ExternalLink';

const REASON_TEXT: Record<EmptyReason, UiTextKey> = {
  setup: 'empty.setup',
  error: 'empty.error',
  noMatch: 'empty.noMatch',
  none: 'empty.none',
  pending: 'empty.pending',
  loading: 'empty.loading',
};

export function EmptyState({ reason, status }: { reason: EmptyReason; status: LayerStatusView }) {
  const t = useTexts();
  return (
    <div className="empty" role="status">
      <p className="empty-title">{t.ui(REASON_TEXT[reason])}</p>
      {status.note === null ? null : <p className="layer-note">{status.note}</p>}
      {status.sourceUrl === null ? null : (
        <ExternalLink href={status.sourceUrl}>{t.ui('empty.openSource')}</ExternalLink>
      )}
    </div>
  );
}
