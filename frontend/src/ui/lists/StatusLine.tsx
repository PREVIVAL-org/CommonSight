/**
 * Status line of a layer: status, source, count, source date, age and "Abdeckung & Quelle" (coverage & source),
 * plus notes on gaps (U-31, U-53, U-54, D-05).
 */
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import { useActions, useTexts } from '../hooks';
import { AvailabilityBadge } from '../parts/AvailabilityBadge';

export function StatusNotices({ status }: { status: LayerStatusView }) {
  const t = useTexts();
  return (
    <>
      {status.availability === 'error' ? (
        <p className="notice" data-tone="error" role="alert">
          {t.ui('list.loadError')}
        </p>
      ) : null}
      {status.lastError === null ? null : <p className="notice">{status.lastError}</p>}
      {status.stale ? <p className="notice">{t.ui('list.stale')}</p> : null}
      {status.issues.length === 0 ? null : (
        <div className="notice" data-tone="info">
          <strong>{t.ui('list.issues')}:</strong>
          <ul className="link-list">
            {status.issues.map((issue) => (
              <li key={issue}>{issue}</li>
            ))}
          </ul>
        </div>
      )}
    </>
  );
}

/** Availability, source and count: announced when they change, unlike the age that moves every minute. */
function StatusSummary({ status }: { status: LayerStatusView }) {
  return (
    <span className="status-line-live" role="status" aria-live="polite">
      <AvailabilityBadge availability={status.availability} />
      {status.source === null ? null : <span>{status.source}</span>}
      <span>{status.countText}</span>
    </span>
  );
}

export function StatusLine({ status }: { status: LayerStatusView }) {
  const t = useTexts();
  const actions = useActions();
  return (
    <div className="panel">
      <div className="status-line">
        <StatusSummary status={status} />
        {status.sourceDate === null ? null : (
          <span className="muted">{t.ui('list.sourceDate', { time: status.sourceDate })}</span>
        )}
        {status.fetchedAge === null ? null : (
          <span className="muted">{t.ui('app.lastFetch', { time: status.fetchedAge })}</span>
        )}
        <button
          type="button"
          className="button button-link"
          onClick={() => actions.openSheet({ type: 'layer', layer: status.layer })}
        >
          {t.ui('list.coverage')}
        </button>
      </div>
      <div className="panel-body">
        <StatusNotices status={status} />
      </div>
    </div>
  );
}
