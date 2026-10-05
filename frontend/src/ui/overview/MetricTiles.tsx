/**
 * Key figures "Messwerte im Überblick" (measurements at a glance), one tile per layer that contributes one, in the
 * order of their rank; each tile opens its detail sheet (U-27).
 */
import type { MetricTileView } from '../../domain/views/metric-tile-view';
import { Sparkline } from '../cards/value-parts';
import { useActions, useAppState, useTexts } from '../hooks';

function MetricTile({ tile }: { tile: MetricTileView }) {
  const t = useTexts();
  const actions = useActions();
  return (
    <button
      type="button"
      className="metric"
      data-highlight={tile.highlight}
      aria-busy={tile.status === 'loading'}
      onClick={() => actions.openSheet(tile.target)}
    >
      <span className="metric-title">{tile.title}</span>
      {tile.value === null ? null : <span className="metric-value">{tile.value}</span>}
      {tile.detail === null ? null : <span>{tile.detail}</span>}
      {tile.history !== null && tile.history.values.length > 1 ? (
        <Sparkline values={tile.history.values} max={tile.history.max} label={tile.history.label} />
      ) : null}
      {tile.time === null ? null : <span className="muted">{tile.time}</span>}
      {tile.hint === null ? null : <span className="metric-hint">{tile.hint}</span>}
      {/* Read with the whole tile (title, value, time, hint), unlike a label that would replace them. */}
      <span className="visually-hidden">{t.ui('metrics.open')}</span>
    </button>
  );
}

export function MetricTiles() {
  const t = useTexts();
  const tiles = useAppState((state, selectors) => selectors.metricTiles(state));
  return (
    <section className="panel overview-metrics" aria-labelledby="cs-metrics-title">
      <div className="panel-head">
        <h2 className="panel-title" id="cs-metrics-title">
          {t.ui('metrics.title')}
        </h2>
      </div>
      <div className="metrics">
        {tiles.map((tile) => (
          <MetricTile key={tile.layer} tile={tile} />
        ))}
      </div>
    </section>
  );
}
