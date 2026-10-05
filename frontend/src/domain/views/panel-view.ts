/**
 * The side panel next to the map with the news items of the layer that contributes it (U-26, layers as plugins L4).
 */
import { UNKNOWN_INTERVAL_SEC } from '../../contract/master-data';
import type { Country, LayerId, Msg } from '../../contract/types';
import type { Availability } from '../layer-availability';
import { PANEL_SLOT } from '../layer-slots';
import type { NewsView } from './news-view';

/** The side panel with the news items of the layer that contributes it (U-26). */
export interface PanelModel {
  layer: LayerId;
  title: Msg;
  views: NewsView[];
  availability: Availability;
  fallbackUrl: string;
  intervalMinutes: number;
}

export function buildPanel(
  views: NewsView[],
  availability: Availability,
  country: Country,
  intervalSec: number | undefined,
): PanelModel | null {
  if (PANEL_SLOT === null) return null;
  const { layer, part } = PANEL_SLOT;
  return {
    layer,
    title: part.title,
    views,
    availability,
    fallbackUrl: part.fallbackLinks[country],
    intervalMinutes: Math.max(1, Math.round((intervalSec ?? UNKNOWN_INTERVAL_SEC) / 60)),
  };
}
