/**
 * Derives the building blocks of the map view: map notice, legend, side panel and tiles (U-23 to U-27), each filled
 * by the layers that contribute it (layers as plugins, L4).
 */
import { catalog } from '../../contract/catalog';
import { cities, LAYER_IDS, regions } from '../../contract/master-data';
import type { Country, Item, LayerId, Snapshot } from '../../contract/types';
import { countriesOf, referenceCountry } from '../../domain/country-choice';
import type { Availability } from '../../domain/layer-availability';
import { referencePoint } from '../../domain/metrics';
import type { LonLat } from '../../domain/nearest-item';
import type { RegionScoped } from '../../domain/region-filter';
import { dataReceivedAtMs, liveStateOf, selectedFailed } from '../../domain/live-status';
import { feedOrder, newsFeed } from '../../domain/news-feed';
import { STATUS_INTERVAL_MS } from '../../domain/refresh-policy';
import { findRegion } from '../../domain/region-options';
import type { NewsTopic } from '../../domain/selection';
import type { LegendEntry, LegendModel } from '../../domain/views/legend-view';
import { buildLegend, buildLegendEntry } from '../../domain/views/legend-view';
import type { LiveView } from '../../domain/views/live-view';
import { liveDisplayOf, secondsUntil, toLiveView } from '../../domain/views/live-view';
import type { MapNoticeView } from '../../domain/views/map-notice-view';
import type { MetricTileView } from '../../domain/views/metric-tile-view';
import { toTileView } from '../../domain/views/metric-tile-view';
import type { NewsView } from '../../domain/views/news-view';
import type { PanelModel } from '../../domain/views/panel-view';
import { buildPanel } from '../../domain/views/panel-view';
import { uiFormat } from '../../domain/views/ui-format';
import type { ViewDeps } from '../../domain/views/view-deps';
import type { UiFormat } from '../../sdk/ui';
import type { AppState } from '../app-state';
import { NOTICE_SLOT, PANEL_SLOT, TILE_SLOTS } from '../../domain/layer-slots';
import { memoizeByKey, memoizeLast } from '../memo';
import { itemsAvailabilityOf, layerStatusOf, snapshotOf } from './data-selectors';
import type { ItemSelectors, ViewIndex } from './item-selectors';
import { viewsFor } from './item-selectors';

export interface OverviewSelectors {
  /** `null` when no layer contributes a notice. */
  mapNotice(state: AppState): MapNoticeView | null;
  legend(state: AppState): LegendModel;
  /** The legend of one layer, also when it is not active (detail sheet); `null` without one. */
  legendEntry(state: AppState, layer: LayerId): LegendEntry | null;
  /** Automatic update below the map (U-50 to U-54). */
  live(state: AppState): LiveView;
  /** `null` when no layer contributes a side panel. */
  panel(state: AppState): PanelModel | null;
  metricTiles(state: AppState): MetricTileView[];
}

function newsViews(index: ViewIndex, items: readonly Item[], topic: NewsTopic, country: Country): NewsView[] {
  return viewsFor(index, newsFeed(items, topic, feedOrder(catalog.newsFeeds, country))).filter(
    (view): view is NewsView => view.kind === 'news',
  );
}

function referenceOf(country: Country, regionId: string | null): LonLat | null {
  return referencePoint(
    findRegion(regions, regionId),
    cities.find((city) => city.country === country) ?? null,
  );
}

type TileInputs = [
  snapshot: Snapshot | undefined,
  matched: readonly Item[],
  reference: LonLat | null,
  availability: Availability,
  format: UiFormat,
  deps: ViewDeps,
];

function buildTile(layer: LayerId, ...inputs: TileInputs): MetricTileView {
  const [snapshot, matched, reference, availability, format, deps] = inputs;
  const slot = TILE_SLOTS.find((entry) => entry.layer === layer);
  if (slot === undefined) throw new Error(`no tile for ${layer}`);
  return toTileView(slot, { snapshot, matched, reference, nowMs: deps.nowMs, format }, availability, deps);
}

function buildNotice(
  scoped: RegionScoped,
  availability: Availability,
  regionName: string | null,
  deps: ViewDeps,
): MapNoticeView | null {
  if (NOTICE_SLOT === null) return null;
  const { layer, part } = NOTICE_SLOT;
  const summary = part.summary({
    availability,
    count: scoped.matched.length,
    unassigned: scoped.unassigned.length,
    regionName: scoped.filtered ? regionName : null,
    format: uiFormat(deps),
  });
  return { ...summary, layer, openLabel: deps.t.msg(part.open) };
}

/** Constant empty list so that memoized selectors stay stable without a snapshot. */
const NO_ITEMS: Item[] = [];

// eslint-disable-next-line max-lines-per-function -- Only collects selectors; each property is a separate, individually tested selector (Architecture 1.3.6).
export function createOverviewSelectors(items: ItemSelectors): OverviewSelectors {
  const legend = memoizeLast(buildLegend);
  const legendEntry = memoizeByKey(buildLegendEntry);
  const entryOf = (state: AppState, layer: LayerId): LegendEntry | null =>
    legendEntry(layer, countriesOf(state.selection.country), snapshotOf(state, layer));
  const live = memoizeLast(toLiveView);
  const news = memoizeLast(newsViews);
  const panel = memoizeLast(buildPanel);
  const reference = memoizeLast(referenceOf);
  const format = memoizeLast(uiFormat);
  const tile = memoizeByKey(buildTile);
  const tiles = memoizeLast((...list: MetricTileView[]) => list);
  const notice = memoizeLast(buildNotice);
  return {
    mapNotice: (state) => {
      if (NOTICE_SLOT === null) return null;
      const deps = items.viewDeps(state);
      return notice(
        items.scoped(state, NOTICE_SLOT.layer),
        itemsAvailabilityOf(state, NOTICE_SLOT.layer),
        findRegion(regions, state.selection.regionId)?.name ?? null,
        deps,
      );
    },
    legend: (state) =>
      legend(
        state.selection.activeLayers,
        ...LAYER_IDS.filter((id) => state.selection.activeLayers.includes(id)).map((id) =>
          entryOf(state, id),
        ),
      ),
    legendEntry: entryOf,
    live: (state) => {
      const selected = countriesOf(state.selection.country);
      const received = selected.map((country) => state.data.statuses[country]?.receivedAtMs);
      const liveState = liveStateOf({
        online: state.env.online,
        receivedAtMs: received,
        failed: selectedFailed(selected, state.data.statusFailed),
      });
      const started = state.data.syncStartedAtMs;
      const next = started === null ? null : started + STATUS_INTERVAL_MS;
      return live(
        liveDisplayOf(liveState, state.data.syncing),
        // The clock ticks every second and may lag the start of a fetch: never more than the interval.
        secondsUntil(next, started === null ? state.env.clockMs : Math.max(state.env.clockMs, started)),
        dataReceivedAtMs(received),
        items.viewDeps(state),
      );
    },
    panel: (state) => {
      if (PANEL_SLOT === null) return null;
      const { layer } = PANEL_SLOT;
      const views = news(
        items.viewIndex(state, layer),
        snapshotOf(state, layer)?.items ?? NO_ITEMS,
        state.selection.newsTopic,
        referenceCountry(state.selection.country),
      );
      return panel(
        views,
        itemsAvailabilityOf(state, layer),
        referenceCountry(state.selection.country),
        layerStatusOf(state, layer)?.intervalSec,
      );
    },
    // With "Alle" the reference place of Germany applies (ADR 0037).
    metricTiles: (state) => {
      const place = reference(referenceCountry(state.selection.country), state.selection.regionId);
      const deps = items.viewDeps(state);
      return tiles(
        ...TILE_SLOTS.map(({ layer }) =>
          tile(
            layer,
            snapshotOf(state, layer),
            items.scoped(state, layer).matched,
            place,
            itemsAvailabilityOf(state, layer),
            format(deps),
            deps,
          ),
        ),
      );
    },
  };
}
