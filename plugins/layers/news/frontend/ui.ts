/**
 * User interface of the layer news: the side panel "Nachrichtenlage" next to the map (U-26); without reachable feeds
 * it links the news page of the country.
 */
import type { LayerUiPart } from '@sdk/ui';

export const ui: LayerUiPart = {
  panel: {
    rank: 10,
    title: { key: 'layer.news.panel.title' },
    fallbackLinks: {
      DE: 'https://www.tagesschau.de/',
      AT: 'https://orf.at/',
      CH: 'https://www.srf.ch/news',
    },
  },
};
