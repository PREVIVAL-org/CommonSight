/**
 * User interface of the layer water: the legend explains the thresholds of the gauges per country (U-25).
 */
import type { LayerUiPart } from '@sdk/ui';

export const ui: LayerUiPart = {
  legend: {
    title: { key: 'layer.water.legend.title' },
    lines: ({ countries }) => [
      { key: 'layer.water.legend.default' },
      ...countries.map((country) => ({ key: `layer.water.legend.${country}` })),
    ],
  },
  officialLinks: true,
};
