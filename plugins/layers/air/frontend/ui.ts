/**
 * User interface of the layer air: the tile shows the first model value of the selected region, highlighted above
 * an EU-AQI of 40 (U-27).
 */
import type { LayerUiPart, ModelValueItem } from '@sdk/ui';

/** Above this EU-AQI the tile is highlighted (U-27). */
export const AIR_HIGHLIGHT_ABOVE = 40;

export const ui: LayerUiPart = {
  tile: {
    rank: 20,
    title: { key: 'layer.air.tile.title' },
    value: ({ matched, format }) => {
      const item = matched.find((entry): entry is ModelValueItem => entry.kind === 'modelValue');
      if (item === undefined) return null;
      return {
        value: `${format.number(item.value, 0)} ${item.unit}`,
        detail: `${item.title} · ${format.text(item.summary)}`,
        time: format.time(item.time),
        highlight: item.value > AIR_HIGHLIGHT_ABOVE,
        itemId: item.id,
      };
    },
  },
};
