/**
 * Which layers fill the slots of the overview (layers as plugins, L4, L-D2): the tiles by rank, the side panel and
 * the map notice from the layer with the highest rank or priority, the short notices of all layers that have one;
 * and what follows from them for the layer list and the data the map view needs.
 * Fixed with the build, like the layer packages themselves.
 */
import { LAYER_IDS, layerMeta } from '../contract/master-data';
import type { LayerId } from '../contract/types';
import { layerUiParts } from '../generated/layer-ui';
import type { LayerNotice, LayerPanel, LayerTile } from '../sdk/ui';

export interface Slot<P> {
  layer: LayerId;
  part: P;
}

function collect<P>(pick: (layer: LayerId) => P | undefined, order: (part: P) => number): Slot<P>[] {
  return LAYER_IDS.flatMap((layer) => {
    const part = pick(layer);
    return part === undefined ? [] : [{ layer, part }];
  }).sort((a, b) => order(a.part) - order(b.part));
}

/** Tiles in "Messwerte im Überblick", ascending by rank. */
export const TILE_SLOTS: readonly Slot<LayerTile>[] = collect(
  (layer) => layerUiParts[layer]?.tile,
  (tile) => tile.rank,
);

/** The side panel next to the map: the one with the highest rank, `null` without one. */
export const PANEL_SLOT: Slot<LayerPanel> | null =
  collect(
    (layer) => layerUiParts[layer]?.panel,
    (panel) => -panel.rank,
  )[0] ?? null;

/** The map notice: the one with the highest priority, `null` without one. */
export const NOTICE_SLOT: Slot<LayerNotice> | null =
  collect(
    (layer) => layerUiParts[layer]?.notice,
    (notice) => -notice.priority,
  )[0] ?? null;

/** Short notices about new entries, per layer. */
export const TOAST_SLOTS: readonly Slot<string>[] = collect(
  (layer) => layerUiParts[layer]?.newEntriesToast,
  () => 0,
);

/** The layers of the layer list: all but the one in the side panel. */
export const LAYER_LIST_IDS: readonly LayerId[] = LAYER_IDS.filter((layer) => layer !== PANEL_SLOT?.layer);

/** The layers the map view needs besides the active ones: map notice, tiles and side panel. */
export const OVERVIEW_LAYER_IDS: readonly LayerId[] = [
  ...(NOTICE_SLOT === null ? [] : [NOTICE_SLOT.layer]),
  ...TILE_SLOTS.map((slot) => slot.layer),
  ...(PANEL_SLOT === null ? [] : [PANEL_SLOT.layer]),
];

/** The layers that explain the colors of their points in a legend. */
export const LEGEND_LAYER_IDS: readonly LayerId[] = LAYER_IDS.filter(
  (layer) => layerUiParts[layer]?.legend !== undefined,
);

/** The list views that have at least one layer; a view without one gets no tab. */
export const LIST_VIEWS: readonly ('measurements' | 'events')[] = (
  ['measurements', 'events'] as const
).filter((view) => LAYER_IDS.some((layer) => layerMeta(layer).view === view));

/** Saved active layers: only layers of the layer list that have a list view (not the one in the side panel). */
export function isPersistableLayer(layer: LayerId): boolean {
  return LAYER_LIST_IDS.includes(layer) && layerMeta(layer).view !== null;
}
