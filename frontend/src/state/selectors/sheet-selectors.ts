/**
 * Derives the contents of the detail sheet: layer, item and items without location assignment (U-41 to U-43).
 */
import { LAYER_IDS, layerMeta, officialLinks, regions } from '../../contract/master-data';
import type { OfficialLink } from '../../contract/master-data';
import type { LayerId } from '../../contract/types';
import type { CountryChoice } from '../../domain/country-choice';
import { countriesOf } from '../../domain/country-choice';
import { findRegion } from '../../domain/region-options';
import type { RegionScoped } from '../../domain/region-filter';
import type { ItemView } from '../../domain/views/item-view';
import type { LayerStatusView } from '../../domain/views/layer-status-view';
import type { AppState } from '../app-state';
import { memoizeByKey, memoizeLast } from '../memo';
import { layerUiParts } from '../../generated/layer-ui';
import type { ItemSelectors, ViewIndex } from './item-selectors';
import { viewsFor } from './item-selectors';
import type { LayerSelectors } from './layer-selectors';

/** Maximum number of boxes in the detail sheet of a layer (U-41). */
export const SHEET_ITEM_LIMIT = 150;

export interface LayerSheetModel {
  status: LayerStatusView;
  views: ItemView[];
  truncated: boolean;
  links: OfficialLink[] | null;
}

export interface ItemSheetModel {
  view: ItemView | null;
  status: LayerStatusView;
}

export interface UnassignedGroup {
  layer: LayerId;
  name: string;
  views: ItemView[];
}

export interface UnassignedModel {
  regionName: string;
  groups: UnassignedGroup[];
  total: number;
}

export interface SheetSelectors {
  layerSheet(state: AppState, layer: LayerId): LayerSheetModel;
  itemSheet(state: AppState, layer: LayerId, itemId: string): ItemSheetModel;
  unassigned(state: AppState): UnassignedModel;
}

type LayerSheetInputs = [
  status: LayerStatusView,
  scoped: RegionScoped,
  index: ViewIndex,
  choice: CountryChoice,
];

function buildLayerSheet(layer: LayerId, ...inputs: LayerSheetInputs): LayerSheetModel {
  const [status, scoped, index, choice] = inputs;
  return {
    status,
    views: viewsFor(index, scoped.matched.slice(0, SHEET_ITEM_LIMIT)),
    truncated: scoped.matched.length > SHEET_ITEM_LIMIT,
    // With "Alle" the official agencies of all countries one after another (ADR 0037).
    links:
      layerUiParts[layer]?.officialLinks === true
        ? countriesOf(choice).flatMap((country) => officialLinks[country])
        : null,
  };
}

const REGIONAL_LAYERS = LAYER_IDS.filter((id) => layerMeta(id).regionFilter);

// eslint-disable-next-line max-lines-per-function -- Only collects selectors; each property is a separate, individually tested selector (Architecture 1.3.6).
export function createSheetSelectors(items: ItemSelectors, layers: LayerSelectors): SheetSelectors {
  const layerSheet = memoizeByKey(buildLayerSheet);
  const itemSheet = memoizeLast((view: ItemView | null, status: LayerStatusView): ItemSheetModel => ({
    view,
    status,
  }));
  const groups = memoizeLast((...inputs: (UnassignedGroup | null)[]) =>
    inputs.filter((group): group is UnassignedGroup => group !== null),
  );
  const group = memoizeByKey(
    (layer: LayerId, scoped: RegionScoped, index: ViewIndex, name: string): UnassignedGroup | null =>
      scoped.unassigned.length === 0 ? null : { layer, name, views: viewsFor(index, scoped.unassigned) },
  );
  const unassigned = memoizeLast((regionName: string, list: UnassignedGroup[]): UnassignedModel => ({
    regionName,
    groups: list,
    total: list.reduce((sum, entry) => sum + entry.views.length, 0),
  }));
  return {
    layerSheet: (state, layer) =>
      layerSheet(
        layer,
        layers.statusView(state, layer),
        items.scoped(state, layer),
        items.viewIndex(state, layer),
        state.selection.country,
      ),
    itemSheet: (state, layer, itemId) =>
      itemSheet(items.itemView(state, layer, itemId), layers.statusView(state, layer)),
    unassigned: (state) => {
      const list = groups(
        ...REGIONAL_LAYERS.map((layer) =>
          group(
            layer,
            items.scoped(state, layer),
            items.viewIndex(state, layer),
            items.viewDeps(state).t.layer(layer),
          ),
        ),
      );
      return unassigned(findRegion(regions, state.selection.regionId)?.name ?? '', list);
    },
  };
}
