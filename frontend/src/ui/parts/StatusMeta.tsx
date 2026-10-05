/**
 * Line with source, count, fetch time and source date of a layer (U-40, U-41).
 */
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import { useTexts } from '../hooks';

export function StatusMeta({ status, withSource }: { status: LayerStatusView; withSource: boolean }) {
  const t = useTexts();
  const parts = [
    withSource ? status.source : null,
    status.countText,
    status.fetchedAt === null ? null : t.ui('sheet.sources.fetched', { time: status.fetchedAt }),
    status.sourceDate === null ? null : t.ui('sheet.sources.sourceDate', { time: status.sourceDate }),
  ];
  return <span className="muted">{parts.filter((part) => part !== null).join(' · ')}</span>;
}
