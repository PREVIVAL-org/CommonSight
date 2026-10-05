/**
 * Reads status, snapshot and availability of a layer for the current selection from the state; with "Alle"
 * merged from the three countries (ADR 0037).
 */
import { layerMeta } from '../../contract/master-data';
import type { LayerId, LayerStatus, Snapshot, StatusResponse, Item } from '../../contract/types';
import { countriesOf } from '../../domain/country-choice';
import type { Availability } from '../../domain/layer-availability';
import { isConnected, layerAvailability } from '../../domain/layer-availability';
import { mergeLayerStatus, mergeSnapshots } from '../../domain/merge-countries';
import { neededLayers } from '../../domain/needed-layers';
import type { SnapshotKey } from '../../domain/snapshot-key';
import { borderKey, snapshotKey, snapshotKeysOf } from '../../domain/snapshot-key';
import type { AppState, StatusState } from '../app-state';
import { memoizeByKey } from '../memo';

function defined<T>(value: T | undefined): value is T {
  return value !== undefined;
}

const mergedStatus = memoizeByKey((_layer: LayerId, ...entries: (LayerStatus | undefined)[]) =>
  mergeLayerStatus(entries.filter(defined)),
);

const mergedSnapshot = memoizeByKey((_layer: LayerId, ...snapshots: (Snapshot | undefined)[]) =>
  mergeSnapshots(snapshots.filter(defined)),
);

/** Available status of the selected countries. */
export function selectedStatuses(state: AppState): StatusState[] {
  return countriesOf(state.selection.country)
    .map((country) => state.data.statuses[country])
    .filter(defined);
}

export function selectedStatusResponses(state: AppState): StatusResponse[] {
  return selectedStatuses(state).map((status) => status.response);
}

export function layerStatusOf(state: AppState, layer: LayerId): LayerStatus | undefined {
  const entries = countriesOf(state.selection.country).map(
    (country) => state.data.statuses[country]?.response.layers[layer],
  );
  return entries.length === 1 ? entries[0] : mergedStatus(layer, ...entries);
}

export function snapshotOf(state: AppState, layer: LayerId): Snapshot | undefined {
  const snapshots = snapshotKeysOf(layer, state.selection.country).map(
    (key) => state.data.snapshots[key]?.snapshot,
  );
  return snapshots.length === 1 ? snapshots[0] : mergedSnapshot(layer, ...snapshots);
}

/** Snapshot of the layer in the border zone beyond DACH; the same for every selection. */
export function borderSnapshotOf(state: AppState, layer: LayerId): Snapshot | undefined {
  return layerMeta(layer).border ? state.data.snapshots[borderKey(layer)]?.snapshot : undefined;
}

/**
 * An item of the layer, wherever it was loaded: the selected countries, the neighbours loaded for "Grenzgebiet" (e.g.
 * "München (Deutschland)" seen from Austria) and the border zone beyond DACH. Ids are unique per source.
 */
export function itemOf(state: AppState, layer: LayerId, itemId: string): Item | undefined {
  for (const loaded of Object.values(state.data.snapshots)) {
    if (loaded?.snapshot.layer !== layer) continue;
    const item = loaded.snapshot.items.find((candidate) => candidate.id === itemId);
    if (item !== undefined) return item;
  }
  return undefined;
}

interface Failures {
  all: boolean;
  some: boolean;
}

/** Load errors per country: snapshot not loadable, or status unreachable and none present (U-54). */
function failuresOf(state: AppState, layer: LayerId): Failures {
  const { data } = state;
  const countries = countriesOf(state.selection.country);
  const statusMissing = countries.filter(
    (country) => data.statusFailed.includes(country) && data.statuses[country] === undefined,
  );
  if (layerMeta(layer).scope === 'global') {
    const [globalKey] = snapshotKeysOf(layer, state.selection.country);
    const failed =
      (globalKey !== undefined && data.failed.includes(globalKey)) ||
      statusMissing.length === countries.length;
    return { all: failed, some: failed };
  }
  const failed = countries.filter(
    (country) => data.failed.includes(snapshotKey(layer, country)) || statusMissing.includes(country),
  ).length;
  return { all: failed === countries.length, some: failed > 0 };
}

export function loadFailed(state: AppState, layer: LayerId): boolean {
  return failuresOf(state, layer).all;
}

/** If a country is missing with "Alle", the layer is at most partially available. */
export function availabilityOf(state: AppState, layer: LayerId): Availability {
  const failures = failuresOf(state, layer);
  const availability = layerAvailability({
    status: layerStatusOf(state, layer),
    snapshot: snapshotOf(state, layer),
    loadFailed: failures.all,
  });
  return failures.some && availability === 'ok' ? 'partial' : availability;
}

/**
 * Availability for everything that shows items (map notice, tiles, lists, sheets): "Wird geladen" as long as a snapshot
 * of the selection is still on its way. The status alone would say "ok" before the items are there, and an empty list
 * would read as "no warnings". The layer list keeps the availability of the status (availabilityOf).
 */
export function itemsAvailabilityOf(state: AppState, layer: LayerId): Availability {
  const availability = availabilityOf(state, layer);
  if (!isConnected(availability)) return availability;
  const pending = snapshotKeysOf(layer, state.selection.country).some(
    (key) => state.data.snapshots[key] === undefined && !state.data.failed.includes(key),
  );
  return pending ? 'loading' : availability;
}

/** Evaluation time: browser time of the latest re-evaluation plus clock offset to the server. */
export function evaluationNowMs(state: AppState): number {
  return state.env.evaluationMs + (selectedStatuses(state)[0]?.clockOffsetMs ?? 0);
}

/** Oldest receipt of a status of the selection; `null` as long as a country's status is missing. */
export function statusReceivedAtMs(state: AppState): number | null {
  const countries = countriesOf(state.selection.country);
  const statuses = selectedStatuses(state);
  if (statuses.length < countries.length) return null;
  return Math.min(...statuses.map((status) => status.receivedAtMs));
}

/** Layers whose snapshots the current selection needs (U-50). */
export function neededLayersOf(state: AppState): LayerId[] {
  return neededLayers({
    view: state.selection.view,
    activeLayers: state.selection.activeLayers,
    listLayers: state.selection.listLayers,
    sheet: state.ui.sheet,
  });
}

/** Loaded version per snapshot key. */
export function loadedVersions(state: AppState): Partial<Record<SnapshotKey, string>> {
  const versions: Partial<Record<SnapshotKey, string>> = {};
  for (const [key, loaded] of Object.entries(state.data.snapshots)) {
    if (loaded !== undefined) versions[key as SnapshotKey] = loaded.version;
  }
  return versions;
}
