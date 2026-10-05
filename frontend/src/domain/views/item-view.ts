/**
 * Selects the view model of an item's kind; a new kind without a view model breaks the type check (U-84).
 */
import type { Item } from '../../contract/types';
import type { EarthquakeView } from './earthquake-view';
import { toEarthquakeView } from './earthquake-view';
import type { IndexView } from './index-view';
import { toIndexView } from './index-view';
import type { MeasurementView } from './measurement-view';
import { toMeasurementView } from './measurement-view';
import type { ModelValueView } from './model-value-view';
import { toModelValueView } from './model-value-view';
import type { NewsView } from './news-view';
import { toNewsView } from './news-view';
import type { TrafficNoticeView } from './traffic-notice-view';
import { toTrafficNoticeView } from './traffic-notice-view';
import type { LayerContext, ViewDeps } from './view-deps';
import type { WarningView } from './warning-view';
import { toWarningView } from './warning-view';

export type ItemView =
  WarningView | MeasurementView | ModelValueView | EarthquakeView | TrafficNoticeView | IndexView | NewsView;

export function assertNever(value: never): never {
  throw new Error(`unhandled kind: ${JSON.stringify(value)}`);
}

export function toItemView(item: Item, context: LayerContext, deps: ViewDeps): ItemView {
  switch (item.kind) {
    case 'warning':
      return toWarningView(item, context, deps);
    case 'measurement':
      return toMeasurementView(item, context, deps);
    case 'modelValue':
      return toModelValueView(item, context, deps);
    case 'earthquake':
      return toEarthquakeView(item, context, deps);
    case 'trafficNotice':
      return toTrafficNoticeView(item, context, deps);
    case 'index':
      return toIndexView(item, context, deps);
    case 'news':
      return toNewsView(item, context, deps);
    default:
      return assertNever(item);
  }
}
