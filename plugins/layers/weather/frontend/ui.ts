/**
 * User interface of the layer weather: the tile shows the first model value of the selected region (U-27).
 */
import type { LayerUiPart, ModelValueItem } from '@sdk/ui';

export const ui: LayerUiPart = {
  tile: {
    rank: 10,
    title: { key: 'layer.weather.tile.title' },
    value: ({ matched, format }) => {
      const item = matched.find((entry): entry is ModelValueItem => entry.kind === 'modelValue');
      if (item === undefined) return null;
      return {
        value: `${format.number(item.value, 1)} ${item.unit}`,
        detail: `${item.title} · ${format.text(item.summary)}`,
        time: format.time(item.time),
        itemId: item.id,
      };
    },
  },
};
