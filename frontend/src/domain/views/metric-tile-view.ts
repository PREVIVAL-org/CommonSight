/**
 * Turns the tile a layer contributes into the view model of a tile in "Messwerte im Überblick" (U-27): the layer
 * computes value and texts, the core adds title, loading and empty state and the sheet the tile opens.
 */
import type { LayerId } from '../../contract/types';
import type { LayerTile, TileInput } from '../../sdk/ui';
import type { Availability } from '../layer-availability';
import type { SheetState } from '../selection';
import type { ViewDeps } from './view-deps';

export interface MetricTileView {
  layer: LayerId;
  title: string;
  status: 'loading' | 'empty' | 'ready';
  value: string | null;
  detail: string | null;
  time: string | null;
  hint: string | null;
  highlight: boolean;
  history: { values: number[]; max: number; label: string } | null;
  target: SheetState;
}

function emptyTile(
  layer: LayerId,
  title: string,
  availability: Availability,
  deps: ViewDeps,
): MetricTileView {
  const loading = availability === 'loading' || availability === 'pending';
  return {
    layer,
    title,
    status: loading ? 'loading' : 'empty',
    value: null,
    detail: deps.t.ui(loading ? 'metrics.loading' : 'metrics.noData'),
    time: null,
    hint: null,
    highlight: false,
    history: null,
    target: { type: 'layer', layer },
  };
}

export function toTileView(
  { layer, part: tile }: { layer: LayerId; part: LayerTile },
  input: TileInput,
  availability: Availability,
  deps: ViewDeps,
): MetricTileView {
  const title = deps.t.msg(tile.title);
  const filled = tile.value(input);
  if (filled === null) return emptyTile(layer, title, availability, deps);
  return {
    layer,
    title,
    status: 'ready',
    value: filled.value,
    detail: filled.detail,
    time: filled.time,
    hint: filled.hint === undefined ? null : deps.t.msg(filled.hint),
    highlight: filled.highlight ?? false,
    history:
      filled.history === undefined
        ? null
        : { values: filled.history.values, max: filled.history.max, label: deps.t.msg(filled.history.label) },
    target:
      filled.itemId === undefined ? { type: 'layer', layer } : { type: 'item', layer, itemId: filled.itemId },
  };
}
