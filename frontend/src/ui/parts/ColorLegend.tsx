/**
 * Meaning of the measuring point colors on the map: orange, red, grey, the thresholds each layer names in its legend
 * (e.g. of the water gauges per country), "Ein grauer Punkt ist keine Entwarnung" (U-25). Shown on demand only: in the
 * sheet "Farben auf der Karte" and in the detail sheets of the layers that have a legend.
 */
import type { CSSProperties } from 'react';
import { LEGEND_LAYER_IDS } from '../../domain/layer-slots';
import type { LegendEntry } from '../../domain/views/legend-view';
import { LEVEL_COLORS } from '../../theme/fixed';
import { useAppState, useTexts } from '../hooks';

function Swatch({ color, hollow }: { color: string | null; hollow?: boolean }) {
  return (
    <span
      className="swatch"
      data-hollow={hollow}
      style={{ '--swatch-color': color ?? 'transparent' } as CSSProperties}
      aria-hidden="true"
    />
  );
}

function Swatches() {
  const t = useTexts();
  return (
    <p className="legend-swatches">
      <span>
        <Swatch color={LEVEL_COLORS.elevated} /> {t.ui('legend.orange')}
      </span>
      <span>
        <Swatch color={LEVEL_COLORS.high} /> {t.ui('legend.red')}
      </span>
      <span>
        <Swatch color={LEVEL_COLORS.unknown} hollow /> {t.ui('legend.gray')}
      </span>
    </p>
  );
}

/** The legend of one layer: the title in bold before the first line, further lines below. */
function LegendText({ entry }: { entry: LegendEntry }) {
  const t = useTexts();
  const [first, ...rest] = entry.lines;
  return (
    <div className="stack">
      <p>
        <strong>{t.msg(entry.title)}:</strong> {first === undefined ? null : t.msg(first)}
      </p>
      {rest.map((line) => (
        <p key={line.key}>{t.msg(line)}</p>
      ))}
    </div>
  );
}

/** `heading`: own heading within another sheet; not needed when the sheet title already says it. */
export function ColorLegend({
  entries,
  heading = true,
}: {
  entries: readonly LegendEntry[];
  heading?: boolean;
}) {
  const t = useTexts();
  return (
    <section className="sheet-section color-legend">
      {heading ? <h3>{t.ui('legend.colors')}</h3> : null}
      <Swatches />
      {entries.map((entry) => (
        <LegendText key={entry.layer} entry={entry} />
      ))}
      <p>
        <strong>{t.ui('legend.gray')}:</strong> {t.ui('legend.gray.text')}{' '}
        <strong>{t.ui('legend.gray.noAllClear')}</strong>
      </p>
      <p>{t.ui('legend.points')}</p>
    </section>
  );
}

/** Sheet "Farben auf der Karte": explains the layers that are on the map right now. */
export function ColorLegendSheet() {
  const t = useTexts();
  const legend = useAppState((state, selectors) => selectors.legend(state));
  if (legend.entries.length === 0) {
    const layers = LEGEND_LAYER_IDS.map((id) => t.layer(id)).join(', ');
    return (
      <p className="muted">
        {t.ui('legend.empty')} {layers === '' ? null : t.ui('legend.empty.layers', { layers })}
      </p>
    );
  }
  return <ColorLegend entries={legend.entries} heading={false} />;
}
