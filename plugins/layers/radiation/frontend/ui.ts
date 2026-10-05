/**
 * User interface of the layer radiation: the legend names the display thresholds from the key figures of the data
 * (Q-RA-05, U-25); the tile shows the station nearest to the reference point, with a hint for an older value (U-27).
 */
import type { LayerUiPart, MeasurementItem, RadiationStats, Snapshot } from '@sdk/ui';
import { nearestItem } from '@sdk/ui';

/** From this age on the tile shows "älterer Wert" (older value, U-27). */
export const TILE_AGE_HINT_MS = 6 * 3600 * 1000;

function colorScale(snapshot: Snapshot | undefined): RadiationStats['colorScale'] | null {
  return snapshot !== undefined && 'colorScale' in snapshot.stats ? snapshot.stats.colorScale : null;
}

export function isOlderThan(time: string | undefined, ageMs: number, nowMs: number): boolean {
  if (time === undefined) return true;
  const at = Date.parse(time);
  return Number.isNaN(at) || nowMs - at > ageMs;
}

export const ui: LayerUiPart = {
  legend: {
    title: { key: 'layer.radiation.legend.title' },
    lines: ({ snapshot }) => {
      const scale = colorScale(snapshot);
      return [
        scale === null
          ? { key: 'layer.radiation.legend.pending' }
          : {
              key: 'layer.radiation.legend.scale',
              params: { elevated: scale.elevated, high: scale.high, maxAgeHours: scale.maxAgeHours },
            },
      ];
    },
  },
  tile: {
    rank: 40,
    title: { key: 'layer.radiation.tile.title' },
    value: ({ snapshot, reference, nowMs, format }) => {
      const stations = (snapshot?.items ?? []).filter(
        (item): item is MeasurementItem => item.kind === 'measurement',
      );
      const item = reference === null ? null : nearestItem(stations, reference);
      if (item === null) return null;
      return {
        value: `${format.number(item.value, 3)} ${item.unit}`,
        detail: item.title,
        time: format.time(item.time),
        ...(isOlderThan(item.time, TILE_AGE_HINT_MS, nowMs)
          ? { hint: { key: 'layer.radiation.tile.olderValue' } }
          : {}),
        itemId: item.id,
      };
    },
  },
};
