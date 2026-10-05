/**
 * Determines from the selection the layers whose snapshots are currently needed (U-50, Architecture 9.3).
 */
import { LAYER_IDS, layerMeta } from '../contract/master-data';
import type { LayerId } from '../contract/types';
import { OVERVIEW_LAYER_IDS } from './layer-slots';
import type { ListLayers, SheetState, ViewId } from './selection';

export interface NeedInputs {
  view: ViewId;
  activeLayers: readonly LayerId[];
  listLayers: ListLayers;
  sheet: SheetState | null;
}

function viewLayers(inputs: NeedInputs): readonly LayerId[] {
  switch (inputs.view) {
    case 'overview':
      // Map notice (U-23), tiles (U-27) and side panel (U-26) of the layers that contribute them.
      return [...inputs.activeLayers, ...OVERVIEW_LAYER_IDS];
    case 'measurements':
      return [inputs.listLayers.measurements];
    case 'events':
      return [inputs.listLayers.events];
  }
}

function sheetLayers(sheet: SheetState | null): readonly LayerId[] {
  if (sheet === null) return [];
  switch (sheet.type) {
    case 'sources':
      return LAYER_IDS;
    case 'layer':
    case 'item':
      return [sheet.layer];
    case 'unassigned':
      return LAYER_IDS.filter((id) => layerMeta(id).regionFilter);
    // The color legend explains active layers only; their snapshots are already needed by the map.
    case 'legend':
      return [];
  }
}

/** Returns the needed layers without duplicates in the order of the registry. */
export function neededLayers(inputs: NeedInputs): LayerId[] {
  const wanted = new Set<LayerId>([...viewLayers(inputs), ...sheetLayers(inputs.sheet)]);
  return LAYER_IDS.filter((id) => wanted.has(id));
}
