/**
 * Describes the user's domain selection (view, layers, detail sheet) as pure types.
 */
import type { LayerId, NewsCategory } from '../contract/types';

export type ViewId = 'overview' | 'measurements' | 'events';

export type NewsTopic = 'all' | NewsCategory;

export type SheetState =
  | { type: 'sources' }
  | { type: 'layer'; layer: LayerId }
  | { type: 'item'; layer: LayerId; itemId: string }
  | { type: 'unassigned' }
  /** Meaning of the measuring point colors (water gauges, radiation; U-25). */
  | { type: 'legend' };

export interface ListLayers {
  measurements: LayerId;
  events: LayerId;
}
