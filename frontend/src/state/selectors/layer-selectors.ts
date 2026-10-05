/**
 * Derives the layer list, status per layer and sources overview from the state (U-20, U-22, U-31, U-40).
 */
import { LAYER_IDS, layerMeta, regions } from '../../contract/master-data';
import type { LayerId, LayerStatus, Snapshot } from '../../contract/types';
import type { Availability } from '../../domain/layer-availability';
import type { RegionScoped } from '../../domain/region-filter';
import { findRegion } from '../../domain/region-options';
import type { SourceSummary } from '../../domain/source-summary';
import { summarizeSources } from '../../domain/source-summary';
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import { toLayerStatusView } from '../../domain/views/layer-status-view';
import type { ViewDeps } from '../../domain/views/view-deps';
import type { AppState } from '../app-state';
import { memoizeByKey, memoizeLast } from '../memo';
import { availabilityOf, itemsAvailabilityOf, layerStatusOf, snapshotOf } from './data-selectors';
import type { ItemSelectors } from './item-selectors';
import { LAYER_LIST_IDS } from '../../domain/layer-slots';

export interface LayerRow {
  layer: LayerId;
  name: string;
  color: string;
  icon: string;
  active: boolean;
  /** Only layers on the map have a toggle; the others (space weather) open the detail sheet (U-21). */
  onMap: boolean;
  availability: Availability;
}

export interface LayerSelectors {
  statusView(state: AppState, layer: LayerId): LayerStatusView;
  layerRows(state: AppState): LayerRow[];
  sourceSummary(state: AppState): SourceSummary;
  allStatusViews(state: AppState): LayerStatusView[];
}

type Availabilities = readonly Availability[];

/** Inputs of a layer's status view; as a tuple so that memoization compares every reference. */
type StatusInputs = [
  entry: LayerStatus | undefined,
  snapshot: Snapshot | undefined,
  availability: Availability,
  scoped: RegionScoped,
  regionId: string | null,
  deps: ViewDeps,
];

function buildStatusView(layer: LayerId, ...inputs: StatusInputs): LayerStatusView {
  const [entry, snapshot, availability, scoped, regionId, deps] = inputs;
  return toLayerStatusView(
    { layer, status: entry, snapshot, availability, scoped, region: findRegion(regions, regionId) },
    deps,
  );
}

// eslint-disable-next-line max-lines-per-function -- Only collects selectors; each property is a separate, individually tested selector (Architecture 1.3.6).
export function createLayerSelectors(items: ItemSelectors): LayerSelectors {
  const status = memoizeByKey(buildStatusView);
  const rows = memoizeLast((active: readonly LayerId[], availabilities: string, deps: ViewDeps): LayerRow[] =>
    LAYER_LIST_IDS.map((id, index) => ({
      layer: id,
      name: deps.t.layer(id),
      color: layerMeta(id).color,
      icon: layerMeta(id).icon,
      active: active.includes(id),
      onMap: layerMeta(id).onMap,
      availability: availabilities.split(',')[index] as Availability,
    })),
  );
  const summary = memoizeLast((key: string) => summarizeSources(key.split(',') as Availabilities));
  const allViews = memoizeLast((...views: LayerStatusView[]) => views);
  const availabilityKey = (state: AppState, ids: readonly LayerId[]): string =>
    ids.map((id) => availabilityOf(state, id)).join(',');
  const selectors: LayerSelectors = {
    statusView: (state, layer) =>
      status(
        layer,
        layerStatusOf(state, layer),
        snapshotOf(state, layer),
        itemsAvailabilityOf(state, layer),
        items.scoped(state, layer),
        state.selection.regionId,
        items.viewDeps(state),
      ),
    layerRows: (state) =>
      rows(state.selection.activeLayers, availabilityKey(state, LAYER_LIST_IDS), items.viewDeps(state)),
    sourceSummary: (state) => summary(availabilityKey(state, LAYER_IDS)),
    allStatusViews: (state) => allViews(...LAYER_IDS.map((id) => selectors.statusView(state, id))),
  };
  return selectors;
}
