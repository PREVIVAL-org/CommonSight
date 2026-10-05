/**
 * The formats of the core as the layer packages get them (numbers, times, their messages; layers as plugins, L4).
 */
import type { UiFormat } from '../../sdk/ui';
import { formatTime } from './card-base-view';
import type { ViewDeps } from './view-deps';

export function uiFormat(deps: ViewDeps): UiFormat {
  return {
    number: (value, digits) => deps.format.number(value, digits),
    time: (iso) => formatTime(iso, deps),
    text: (message) => deps.t.msg(message),
  };
}
