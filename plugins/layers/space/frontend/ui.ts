/**
 * User interface of the layer space: the tile shows the planetary Kp index with its course and the NOAA scales,
 * highlighted from Kp 5 (storm, U-27).
 */
import type { LayerUiPart, Snapshot, SpaceStats, UiFormat } from '@sdk/ui';

/** Upper end of the Kp scale. */
const KP_MAX = 9;
/** From this Kp on the tile is highlighted (geomagnetic storm, G1). */
const KP_STORM = 5;

export function isSpaceStats(stats: Snapshot['stats']): stats is SpaceStats {
  return 'history' in stats && 'kp' in stats;
}

function scale(value: number | null, format: UiFormat): string {
  return value === null ? format.text({ key: 'layer.space.tile.scaleMissing' }) : format.number(value, 0);
}

export const ui: LayerUiPart = {
  tile: {
    rank: 30,
    title: { key: 'layer.space.tile.title' },
    value: ({ snapshot, format }) => {
      const stats = snapshot !== undefined && isSpaceStats(snapshot.stats) ? snapshot.stats : null;
      if (stats === null || stats.kp === null) return null;
      return {
        value: format.text({ key: 'layer.space.tile.kp', params: { value: format.number(stats.kp, 2) } }),
        detail: format.text({
          key: 'layer.space.tile.scales',
          params: { G: scale(stats.G, format), R: scale(stats.R, format), S: scale(stats.S, format) },
        }),
        time: format.time(snapshot?.generatedAt),
        highlight: stats.kp >= KP_STORM,
        history: { values: stats.history, max: KP_MAX, label: { key: 'layer.space.tile.history' } },
      };
    },
  },
};
