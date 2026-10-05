/**
 * The legend of the map from the legends the layers contribute (U-25, layers as plugins L3).
 */
import { layerMeta } from '../../contract/master-data';
import type { Country, LayerId, Msg, Snapshot } from '../../contract/types';
import { layerUiParts } from '../../generated/layer-ui';

/** The meaning of the point colors of one layer, as its package describes it (U-25, layers as plugins L3). */
export interface LegendEntry {
  layer: LayerId;
  title: Msg;
  /** The first line follows the title. */
  lines: Msg[];
}

export interface LegendModel {
  activeCount: number;
  /** The active layers that explain their point colors, in the order of the registry. */
  entries: LegendEntry[];
}

export function buildLegendEntry(
  layer: LayerId,
  countries: readonly Country[],
  snapshot: Snapshot | undefined,
): LegendEntry | null {
  const legend = layerUiParts[layer]?.legend;
  return legend === undefined
    ? null
    : { layer, title: legend.title, lines: legend.lines({ countries, snapshot }) };
}

export function buildLegend(active: readonly LayerId[], ...entries: (LegendEntry | null)[]): LegendModel {
  return {
    activeCount: active.filter((id) => layerMeta(id).onMap).length,
    entries: entries.filter((entry): entry is LegendEntry => entry !== null),
  };
}
