/**
 * Decides whether switching on a layer also opens its detail sheet (U-21).
 */
import { layerMeta } from '../contract/master-data';
import type { LayerId, LayerStatusValue } from '../contract/types';

/** Layers that cannot be shown on the map and layers without data access show their detail sheet. */
export function opensSheetOnActivate(layer: LayerId, status: LayerStatusValue | undefined): boolean {
  return !layerMeta(layer).onMap || status === 'setup';
}

export function toggleLayer(active: readonly LayerId[], layer: LayerId): LayerId[] {
  return active.includes(layer) ? active.filter((id) => id !== layer) : [...active, layer];
}
