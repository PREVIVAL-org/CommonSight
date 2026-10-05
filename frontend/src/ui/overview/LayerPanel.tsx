/**
 * Layer list: one row per map layer with icon, name, status indicator and switch; layers without a map
 * (space weather) set apart at the end with "Anzeigen" (show) instead of a switch; counter, source status and
 * "Datenquellen" (data sources) (U-20 to U-22, U-40).
 */
import * as Switch from '@radix-ui/react-switch';
import { Database, Eye } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { useId } from 'react';
import { NOTICE_SLOT } from '../../domain/layer-slots';
import type { LayerRow } from '../../state/selectors/layer-selectors';
import { useActions, useAppState, useTexts } from '../hooks';
import { IconButton } from '../parts/IconButton';
import { LayerIcon } from '../parts/LayerIcon';
import { StatusIndicator } from '../parts/StatusIndicator';

function RowFrame({ row, labelFor, children }: { row: LayerRow; labelFor?: string; children: ReactNode }) {
  const style = { '--layer-color': `var(--_layer-${row.layer}, ${row.color})` } as CSSProperties;
  return (
    <li className="layer-row" data-active={row.onMap && row.active} style={style}>
      <span className="layer-icon">
        <LayerIcon icon={row.icon} />
      </span>
      <label className="layer-label" htmlFor={labelFor}>
        <span className="layer-name">{row.name}</span>
      </label>
      <StatusIndicator availability={row.availability} />
      {children}
    </li>
  );
}

function LayerToggle({ row }: { row: LayerRow }) {
  const actions = useActions();
  const id = useId();
  return (
    <RowFrame row={row} labelFor={id}>
      <Switch.Root
        id={id}
        className="switch"
        checked={row.active}
        aria-label={row.name}
        onCheckedChange={() => actions.toggleLayer(row.layer)}
      >
        <Switch.Thumb className="switch-thumb" />
      </Switch.Root>
    </RowFrame>
  );
}

/** Layer without map display: only opens its detail sheet (U-21). */
function LayerViewer({ row }: { row: LayerRow }) {
  const t = useTexts();
  const actions = useActions();
  return (
    <RowFrame row={row}>
      <IconButton
        label={t.ui('layers.show', { layer: row.name })}
        onClick={() => actions.openSheet({ type: 'layer', layer: row.layer })}
      >
        <Eye size={18} aria-hidden="true" />
      </IconButton>
    </RowFrame>
  );
}

/** Source status as text, below it "Datenquellen" leading to the sources overview (U-22, U-40). */
function SourceStatus() {
  const t = useTexts();
  const actions = useActions();
  const summary = useAppState((state, selectors) => selectors.sourceSummary(state));
  return (
    <>
      <p className="source-status">
        <strong>{t.ui('layers.sourceStatus')}:</strong>{' '}
        {t.ui('layers.connected', { count: summary.connected, total: summary.total })}
        {summary.unreachable > 0 ? ` · ${t.ui('layers.unreachable', { count: summary.unreachable })}` : null}
      </p>
      <button type="button" className="button" onClick={() => actions.openSheet({ type: 'sources' })}>
        <Database size={16} aria-hidden="true" /> {t.ui('app.sources')}
      </button>
    </>
  );
}

function OtherLayers({ rows }: { rows: LayerRow[] }) {
  const t = useTexts();
  if (rows.length === 0) return null;
  return (
    <ul className="layer-list layer-list-other" aria-label={t.ui('layers.other')}>
      {rows.map((row) => (
        <LayerViewer key={row.layer} row={row} />
      ))}
    </ul>
  );
}

/** Shows the entries of the layer with the map notice (today the warnings). */
function BrowseButton() {
  const t = useTexts();
  const actions = useActions();
  if (NOTICE_SLOT === null) return null;
  const { layer, part } = NOTICE_SLOT;
  return (
    <button type="button" className="button" onClick={() => actions.openEntries(layer)}>
      {t.msg(part.browse)}
    </button>
  );
}

function LayerFoot() {
  const t = useTexts();
  return (
    <div className="layer-foot">
      <SourceStatus />
      <p className="muted">{t.ui('layers.hint')}</p>
      <BrowseButton />
    </div>
  );
}

export function LayerPanel() {
  const t = useTexts();
  const rows = useAppState((state, selectors) => selectors.layerRows(state));
  const mapRows = rows.filter((row) => row.onMap);
  const active = mapRows.filter((row) => row.active).length;
  return (
    <section className="panel overview-layers" part="layers" aria-labelledby="cs-layers-title">
      <div className="panel-head">
        <h2 className="panel-title" id="cs-layers-title">
          {t.ui('layers.title')}
        </h2>
        <span className="muted">{t.ui('layers.count', { active, total: mapRows.length })}</span>
      </div>
      <ul className="layer-list">
        {mapRows.map((row) => (
          <LayerToggle key={row.layer} row={row} />
        ))}
      </ul>
      <OtherLayers rows={rows.filter((row) => !row.onMap)} />
      <LayerFoot />
    </section>
  );
}
