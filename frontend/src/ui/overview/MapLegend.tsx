/**
 * Map legend: number of active layers (layers are recognized by their color in the layer list, U-24) and, with a
 * layer on the map that explains its point colors, the link "Legende" to the meaning of the point colors (U-25), plus the
 * countdown of the automatic update (U-50 to U-54); on narrow screens icon and seconds only.
 */
import { RefreshCw, TriangleAlert, WifiOff } from 'lucide-react';
import type { LiveDisplay } from '../../domain/views/live-view';
import { useActions, useAppState, useTexts } from '../hooks';

function LegendLink() {
  const t = useTexts();
  const actions = useActions();
  return (
    <button
      type="button"
      className="button button-link"
      aria-label={t.ui('legend.open')}
      onClick={() => actions.openSheet({ type: 'legend' })}
    >
      {t.ui('legend.link')}
    </button>
  );
}

/** `turn` changes with every fetch; the new key restarts the full turn of the arrows, however short the fetch is. */
function LiveIcon({ state, turn }: { state: LiveDisplay; turn: number | null }) {
  if (state === 'offline') return <WifiOff size={14} aria-hidden="true" />;
  if (state === 'failed') return <TriangleAlert size={14} aria-hidden="true" />;
  return <RefreshCw key={turn ?? 0} size={14} aria-hidden="true" className="live-icon" />;
}

function LiveIndicator() {
  const live = useAppState((state, selectors) => selectors.live(state));
  const turn = useAppState((state) => state.data.syncStartedAtMs);
  return (
    <span className="live" data-state={live.state} title={live.hint}>
      <LiveIcon state={live.state} turn={turn} />
      <span className="live-text">{live.text}</span>
      <span className="live-short" aria-hidden="true">
        {live.short}
      </span>
    </span>
  );
}

export function MapLegend() {
  const t = useTexts();
  const legend = useAppState((state, selectors) => selectors.legend(state));
  return (
    <div className="legend" part="legend" aria-label={t.ui('legend.title')} role="group">
      <span className="legend-info">
        <span className="muted">{t.ui('legend.activeLayers', { count: legend.activeCount })}</span>
        <LiveIndicator />
      </span>
      {legend.entries.length > 0 ? <LegendLink /> : null}
    </div>
  );
}
