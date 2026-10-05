/**
 * Derives the lists of the views "Messwerte" and "Warnungen & Ereignisse" (U-30 to U-36).
 */
import { layerMeta, layerRegistry } from '../../contract/master-data';
import type { Item, LayerId } from '../../contract/types';
import type { EmptyReason } from '../../domain/empty-state';
import { emptyReason } from '../../domain/empty-state';
import type { ItemView } from '../../domain/views/item-view';
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import type { TableRowView } from '../../domain/views/table-row-view';
import { toTableRow } from '../../domain/views/table-row-view';
import type { ViewDeps } from '../../domain/views/view-deps';
import type { AppState } from '../app-state';
import { memoizeByKey } from '../memo';
import type { ItemSelectors, ViewIndex } from './item-selectors';
import { viewsFor } from './item-selectors';
import type { LayerSelectors } from './layer-selectors';

export type ListViewId = 'measurements' | 'events';

export interface LayerOption {
  layer: LayerId;
  name: string;
}

export interface ListModel {
  layer: LayerId;
  options: LayerOption[];
  status: LayerStatusView;
  views: ItemView[];
  rows: TableRowView[];
  shown: number;
  remaining: number;
  empty: EmptyReason | null;
  overregional: boolean;
  unassigned: number;
  /** Section "Grenzgebiet": items beyond the border near the selection; not counted above. */
  border: ItemView[];
  borderRows: TableRowView[];
  /** All items of the section and those beyond the page size ("Mehr anzeigen" enlarges both lists). */
  borderTotal: number;
  borderRemaining: number;
}

export interface ListSelectors {
  list(state: AppState, view: ListViewId): ListModel;
}

type ListInputs = [
  status: LayerStatusView,
  scopedTotal: number,
  unassigned: number,
  filtered: Item[],
  limit: number,
  index: ViewIndex,
  deps: ViewDeps,
  border: Item[],
  borderIndex: ViewIndex,
  borderLimit: number,
];

function optionsFor(view: ListViewId, deps: ViewDeps): LayerOption[] {
  return layerRegistry
    .filter((meta) => meta.view === view)
    .map((meta) => ({ layer: meta.id, name: deps.t.layer(meta.id) }));
}

function buildList(key: string, ...inputs: ListInputs): ListModel {
  const [view, layer] = key.split('|') as [ListViewId, LayerId];
  const [status, scopedTotal, unassigned, filtered, limit, index, deps, border, borderIndex, borderLimit] =
    inputs;
  const views = viewsFor(index, filtered.slice(0, limit));
  const borderViews = viewsFor(borderIndex, border.slice(0, borderLimit));
  return {
    layer,
    options: optionsFor(view, deps),
    status,
    views,
    rows: view === 'measurements' ? views.map(toTableRow) : [],
    shown: filtered.length,
    remaining: Math.max(0, filtered.length - limit),
    empty: emptyReason(status.availability, scopedTotal, filtered.length),
    overregional: !layerMeta(layer).regionFilter,
    unassigned,
    border: borderViews,
    borderRows: view === 'measurements' ? borderViews.map(toTableRow) : [],
    borderTotal: border.length,
    borderRemaining: Math.max(0, border.length - borderLimit),
  };
}

export function createListSelectors(items: ItemSelectors, layers: LayerSelectors): ListSelectors {
  const list = memoizeByKey(buildList);
  return {
    list: (state, view) => {
      const layer = state.selection.listLayers[view];
      const scoped = items.scoped(state, layer);
      return list(
        `${view}|${layer}`,
        layers.statusView(state, layer),
        scoped.matched.length,
        scoped.unassigned.length,
        items.filtered(state, layer),
        state.selection.listLimit,
        items.viewIndex(state, layer),
        items.viewDeps(state),
        items.border(state, layer),
        items.borderIndex(state, layer),
        state.selection.borderLimit,
      );
    },
  };
}
