/**
 * Prepares status, source, data timestamp and notice of a layer for the status line, sources overview
 * and detail sheet (U-31, U-40, U-41, U-16).
 */
import type { Region } from '../../contract/master-data';
import { UNKNOWN_INTERVAL_SEC } from '../../contract/master-data';
import type { LayerId, LayerStatus, Snapshot } from '../../contract/types';
import type { Availability } from '../layer-availability';
import type { RegionScoped } from '../region-filter';
import type { ViewDeps } from './view-deps';

export interface LayerStatusView {
  layer: LayerId;
  name: string;
  availability: Availability;
  availabilityText: string;
  source: string | null;
  sourceUrl: string | null;
  /** Number of items shown (after the region filter). */
  count: number;
  countText: string;
  fetchedAt: string | null;
  /** Age of the last fetch, e.g. "vor 3 Minuten" (3 minutes ago, U-53). */
  fetchedAge: string | null;
  /**
   * Compact time and outcome of the last fetch for the interval list, e.g. "letzter Abruf: 01.10., 14:00" or
   * "letzter Abruf: 01.10., 12:15, veraltet" (last fetch, stale); `null` without a fetch (U-40).
   */
  lastFetch: string | null;
  sourceDate: string | null;
  /** The state is older than expected for the layer (status `stale`). */
  stale: boolean;
  note: string | null;
  /** What the sources cover, one line per source, shown below the note (e.g. the motorways of the Autobahn GmbH). */
  coverage: string[];
  /** Addition to the notice when a region is chosen (U-16). */
  regionNote: string | null;
  issues: string[];
  /** "Letzte Aktualisierung fehlgeschlagen ..." (last update failed, D-05); `null` without a failure. */
  lastError: string | null;
  /** Fetch interval per the status endpoint, otherwise per the registry, in minutes (U-40). */
  intervalMinutes: number;
}

export interface LayerStatusInputs {
  layer: LayerId;
  status: LayerStatus | undefined;
  snapshot: Snapshot | undefined;
  availability: Availability;
  scoped: RegionScoped;
  region: Region | null;
}

function regionNote(inputs: LayerStatusInputs, deps: ViewDeps): string | null {
  if (!inputs.scoped.filtered || inputs.region === null) return null;
  return deps.t.ui('note.region', {
    region: inputs.region.name,
    matched: inputs.scoped.matched.length,
    unassigned: inputs.scoped.unassigned.length,
  });
}

function lastError(status: LayerStatus, deps: ViewDeps): string | null {
  if (status.lastError === null) return null;
  const time = deps.format.dateTime(status.lastError.at) ?? deps.t.ui('time.none');
  return `${deps.t.ui('list.lastError', { time })} ${deps.t.msg(status.lastError.message)}`;
}

type SourceFields = Pick<
  LayerStatusView,
  'source' | 'sourceUrl' | 'note' | 'coverage' | 'issues' | 'sourceDate'
>;
type TimeFields = Pick<LayerStatusView, 'fetchedAt' | 'fetchedAge' | 'lastFetch' | 'stale' | 'lastError'>;

/** A failure since the last success weighs more than a stale state. */
function lastFetch(status: LayerStatus, deps: ViewDeps): string | null {
  const time = deps.format.dateTime(status.checkedAt);
  if (time === null) return null;
  if (status.lastError !== null) return deps.t.ui('sheet.sources.lastFetchFailed', { time });
  return deps.t.ui(status.stale ? 'sheet.sources.lastFetchStale' : 'sheet.sources.lastFetch', { time });
}

/** Details from the snapshot; without a snapshot, where available, from the status. */
function sourceFields(
  snapshot: Snapshot | undefined,
  status: LayerStatus | undefined,
  deps: ViewDeps,
): SourceFields {
  if (snapshot === undefined) {
    return {
      source: null,
      sourceUrl: null,
      note: null,
      coverage: [],
      issues: (status?.issues ?? []).map((issue) => deps.t.msg(issue)),
      sourceDate: deps.format.dateTime(status?.sourceUpdatedAt),
    };
  }
  return {
    source: snapshot.source,
    sourceUrl: snapshot.sourceUrl,
    note: deps.t.msg(snapshot.note),
    coverage: snapshot.coverage.map((line) => deps.t.msg(line)),
    issues: snapshot.issues.map((issue) => deps.t.msg(issue)),
    sourceDate: deps.format.dateTime(snapshot.updatedAt),
  };
}

function timeFields(status: LayerStatus | undefined, deps: ViewDeps): TimeFields {
  if (status === undefined) {
    return { fetchedAt: null, fetchedAge: null, lastFetch: null, stale: false, lastError: null };
  }
  return {
    fetchedAt: deps.format.dateTime(status.checkedAt),
    fetchedAge: deps.format.age(status.checkedAt, deps.nowMs),
    lastFetch: lastFetch(status, deps),
    stale: status.stale,
    lastError: lastError(status, deps),
  };
}

export function toLayerStatusView(inputs: LayerStatusInputs, deps: ViewDeps): LayerStatusView {
  const count = inputs.scoped.matched.length;
  return {
    layer: inputs.layer,
    name: deps.t.layer(inputs.layer),
    availability: inputs.availability,
    availabilityText: deps.t.ui(`availability.${inputs.availability}`),
    count,
    countText: deps.t.ui('list.count', { count }),
    regionNote: regionNote(inputs, deps),
    intervalMinutes: Math.max(1, Math.round((inputs.status?.intervalSec ?? UNKNOWN_INTERVAL_SEC) / 60)),
    ...sourceFields(inputs.snapshot, inputs.status, deps),
    ...timeFields(inputs.status, deps),
  };
}
