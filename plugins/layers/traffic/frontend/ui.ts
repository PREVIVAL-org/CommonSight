/**
 * User interface of the layer traffic: the legend explains why jams are red (U-25).
 */
import type { LayerUiPart } from '@sdk/ui';

export const ui: LayerUiPart = {
  legend: {
    title: { key: 'layer.traffic.legend.title' },
    lines: () => [{ key: 'layer.traffic.legend.colors' }],
  },
};
