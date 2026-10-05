/**
 * Derives the items of a layer: filtered by region and keyword and as view models, memoized; separately the items
 * beyond the border of the selection (border-selectors.ts).
 */
import { layerMeta } from '../../contract/master-data';
import type { Item, LayerId, Snapshot } from '../../contract/types';
import type { RegionScoped } from '../../domain/region-filter';
import { scopeToRegion } from '../../domain/region-filter';
import { filterByText } from '../../domain/text-filter';
import type { ItemView } from '../../domain/views/item-view';
import { toItemView } from '../../domain/views/item-view';
import type { ViewDeps } from '../../domain/views/view-deps';
import type { Formats } from '../../i18n/formats';
import type { Translator } from '../../i18n/translator';
import type { AppState } from '../app-state';
import { memoizeByKey, memoizeLast } from '../memo';
import { createBorderSelectors } from './border-selectors';
import { evaluationNowMs, snapshotOf } from './data-selectors';

export interface TextServices {
  t: Translator;
  format: Formats;
}

export type ViewIndex = ReadonlyMap<string, ItemView>;

export interface ItemSelectors {
  viewDeps(state: AppState): ViewDeps;
  scoped(state: AppState, layer: LayerId): RegionScoped;
  /** Items after region and text filter (U-30). */
  filtered(state: AppState, layer: LayerId): Item[];
  /** View models of all items of a layer, by ID. */
  viewIndex(state: AppState, layer: LayerId): ViewIndex;
  /** Also finds items of the border zone (detail sheet after a click on the map). */
  itemView(state: AppState, layer: LayerId, itemId: string): ItemView | null;
  /** Items beyond the border near the selection after the text filter (section "Grenzgebiet"): DACH neighbours, border zone. */
  border(state: AppState, layer: LayerId): Item[];
  /** Items beyond the border near the selection without text filter, for the map: the same rule as the list. */
  borderAll(state: AppState, layer: LayerId): Item[];
  borderIndex(state: AppState, layer: LayerId): ViewIndex;
}

/** Constant empty list, so that memoized consumers stay stable without a border snapshot. */
const NO_ITEMS: Item[] = [];

/** Maps items to their view models (for memoized UI models). */
export function viewsFor(index: ViewIndex, items: readonly Item[]): ItemView[] {
  return items.flatMap((item) => {
    const view = index.get(item.id);
    return view === undefined ? [] : [view];
  });
}

function buildIndex(layer: LayerId, snapshot: Snapshot | undefined, deps: ViewDeps): ViewIndex {
  const context = { layer, source: snapshot?.source ?? '' };
  return new Map((snapshot?.items ?? []).map((item) => [item.id, toItemView(item, context, deps)]));
}

// eslint-disable-next-line max-lines-per-function -- Only collects selectors; each property is a separate, individually tested selector (Architecture 1.3.6).
export function createItemSelectors(texts: TextServices): ItemSelectors {
  const deps = memoizeLast((nowMs: number): ViewDeps => ({ t: texts.t, format: texts.format, nowMs }));
  const scoped = memoizeByKey((layer: LayerId, snapshot: Snapshot | undefined, regionId: string | null) =>
    scopeToRegion(snapshot?.items ?? [], regionId, layerMeta(layer).regionFilter),
  );
  const filtered = memoizeByKey((_layer: LayerId, items: Item[], query: string) =>
    filterByText(items, query),
  );
  const index = memoizeByKey(buildIndex);
  const beyond = createBorderSelectors();
  const borderFiltered = memoizeByKey((_layer: LayerId, items: Item[], query: string) =>
    filterByText(items, query),
  );
  const selectors: ItemSelectors = {
    viewDeps: (state) => deps(evaluationNowMs(state)),
    scoped: (state, layer) => scoped(layer, snapshotOf(state, layer), state.selection.regionId),
    filtered: (state, layer) =>
      filtered(layer, selectors.scoped(state, layer).matched, state.selection.query),
    viewIndex: (state, layer) => index(layer, snapshotOf(state, layer), selectors.viewDeps(state)),
    itemView: (state, layer, itemId) =>
      selectors.viewIndex(state, layer).get(itemId) ??
      selectors.borderIndex(state, layer).get(itemId) ??
      null,
    border: (state, layer) =>
      !state.selection.borderZone
        ? NO_ITEMS
        : borderFiltered(layer, beyond.near(state, layer), state.selection.query),
    borderAll: (state, layer) => (state.selection.borderZone ? beyond.near(state, layer) : NO_ITEMS),
    borderIndex: (state, layer) => beyond.index(state, layer, selectors.viewDeps(state)),
  };
  return selectors;
}
