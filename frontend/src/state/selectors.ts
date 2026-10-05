/**
 * Provides all derived values of the state as memoized selectors (Architecture 9.2).
 */
import type { HeaderSelectors } from './selectors/header-selectors';
import { createHeaderSelectors } from './selectors/header-selectors';
import type { ItemSelectors, TextServices } from './selectors/item-selectors';
import { createItemSelectors } from './selectors/item-selectors';
import type { LayerSelectors } from './selectors/layer-selectors';
import { createLayerSelectors } from './selectors/layer-selectors';
import type { ListSelectors } from './selectors/list-selectors';
import { createListSelectors } from './selectors/list-selectors';
import type { OverviewSelectors } from './selectors/overview-selectors';
import { createOverviewSelectors } from './selectors/overview-selectors';
import type { SheetSelectors } from './selectors/sheet-selectors';
import { createSheetSelectors } from './selectors/sheet-selectors';

export type { TextServices } from './selectors/item-selectors';

export type Selectors = ItemSelectors &
  LayerSelectors &
  HeaderSelectors &
  OverviewSelectors &
  ListSelectors &
  SheetSelectors;

export function createSelectors(texts: TextServices, collator: Intl.Collator): Selectors {
  const items = createItemSelectors(texts);
  const layers = createLayerSelectors(items);
  return {
    ...items,
    ...layers,
    ...createHeaderSelectors(texts, collator),
    ...createOverviewSelectors(items),
    ...createListSelectors(items, layers),
    ...createSheetSelectors(items, layers),
  };
}
