/**
 * Detail sheet "Datenquellen & Abdeckung" (data sources & coverage): intervals, status per layer incl. news, and
 * data use (U-40, R-01, R-02, R-05); for "Alle" (all) with merged status and the boundary data of all countries
 * (ADR 0037); license and source code of CommonSight (AGPL-3.0).
 */
import { attribution, LAYER_IDS, sources } from '../../contract/master-data';
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import { APP_VERSION, LICENSE_URL, REPOSITORY_URL } from '../../version';
import { useActions, useAppState, useTexts } from '../hooks';
import { AvailabilityBadge } from '../parts/AvailabilityBadge';
import { ExternalLink } from '../parts/ExternalLink';
import { StatusMeta } from '../parts/StatusMeta';
import { LayerNote } from '../parts/LayerNote';

function SourceRow({ status }: { status: LayerStatusView }) {
  const t = useTexts();
  const actions = useActions();
  return (
    <li className="source-row">
      <div className="source-row-head">
        <strong>{status.name}</strong>
        <AvailabilityBadge availability={status.availability} />
      </div>
      <LayerNote note={status.note} coverage={status.coverage} />
      <StatusMeta status={status} withSource />
      <span className="card-footer">
        {status.sourceUrl === null ? null : (
          <ExternalLink href={status.sourceUrl}>{t.ui('empty.openSource')}</ExternalLink>
        )}
        <button
          type="button"
          className="button button-link"
          onClick={() => actions.openSheet({ type: 'layer', layer: status.layer })}
        >
          {t.ui('sheet.sources.viewData')}
        </button>
      </span>
    </li>
  );
}

function Rhythms({ statuses }: { statuses: LayerStatusView[] }) {
  const t = useTexts();
  return (
    <section className="sheet-section">
      <p>{t.ui('sheet.sources.rhythm')}</p>
      <ul className="link-list">
        {statuses.map((status) => (
          <li key={status.layer}>
            {t.ui('sheet.sources.interval', { layer: status.name, count: status.intervalMinutes })}
            {status.lastFetch === null ? null : <span className="muted"> · {status.lastFetch}</span>}
          </li>
        ))}
      </ul>
    </section>
  );
}

/** The attributions of all sources in the order of the layers, each text only once (e.g. one feed for two layers). */
function sourceAttributions() {
  const byLayer = [...sources].sort(
    (a, b) => LAYER_IDS.indexOf(a.layer) - LAYER_IDS.indexOf(b.layer) || a.order - b.order,
  );
  const seen = new Set<string>();
  return byLayer.filter((source) => !seen.has(source.attribution.text) && seen.add(source.attribution.text));
}

function SourceAttributions() {
  return sourceAttributions().map((source) => (
    <li key={source.id}>
      {source.attribution.text} <ExternalLink href={source.attribution.url}>{source.name}</ExternalLink>
    </li>
  ));
}

function DataUse() {
  const t = useTexts();
  const selected = useAppState((state, selectors) => selectors.selectedCountries(state));
  return (
    <section className="sheet-section">
      <h3>{t.ui('sheet.sources.dataUse')}</h3>
      <ul className="stack">
        <SourceAttributions />
        {attribution.dataUse.map((entry) => (
          <li key={entry.label}>
            <strong>{entry.label}:</strong> {entry.text}{' '}
            <ExternalLink href={entry.url}>{entry.label}</ExternalLink>
          </li>
        ))}
        {selected.map((country) => (
          <li key={country}>
            <ExternalLink href={attribution.regions[country].url}>
              {attribution.regions[country].text}
            </ExternalLink>
          </li>
        ))}
      </ul>
      <p className="muted">{t.ui('sheet.sources.originals')}</p>
      <p className="muted">{t.ui('sheet.sources.privacy')}</p>
    </section>
  );
}

/** License and source code of CommonSight itself (AGPL-3.0, section 13); the only place in the UI for it. */
function Software() {
  const t = useTexts();
  return (
    <section className="sheet-section">
      <h3>{t.ui('sheet.sources.software')}</h3>
      <p>{t.ui('sheet.sources.license', { version: APP_VERSION })}</p>
      <span className="card-footer">
        <ExternalLink href={REPOSITORY_URL}>{t.ui('sheet.sources.sourceCode')}</ExternalLink>
        <ExternalLink href={LICENSE_URL}>{t.ui('sheet.sources.licenseText')}</ExternalLink>
      </span>
    </section>
  );
}

export function SourcesSheet() {
  const statuses = useAppState((state, selectors) => selectors.allStatusViews(state));
  return (
    <>
      <Rhythms statuses={statuses} />
      <ul className="stack">
        {statuses.map((status) => (
          <SourceRow key={status.layer} status={status} />
        ))}
      </ul>
      <DataUse />
      <Software />
    </>
  );
}
