/**
 * Turns a traffic notice into the view model with road, type, start and description (U-80).
 */
import type { TrafficNoticeItem } from '../../contract/types';
import type { CardBaseView } from './card-base-view';
import { cardBase, layerCategory } from './card-base-view';
import { truncate } from './truncate';
import type { LayerContext, ViewDeps } from './view-deps';

export interface TrafficNoticeView {
  kind: 'trafficNotice';
  base: CardBaseView;
  road: string | null;
  noticeType: string | null;
  start: string | null;
  description: string | null;
  summary: string | null;
}

const SUMMARY_LENGTH = 180;

export function toTrafficNoticeView(
  item: TrafficNoticeItem,
  context: LayerContext,
  deps: ViewDeps,
): TrafficNoticeView {
  const description = item.description ?? null;
  return {
    kind: 'trafficNotice',
    base: cardBase(item, context, layerCategory(context.layer, deps), deps),
    road: item.road ?? null,
    noticeType: item.noticeType ?? null,
    start: deps.format.dateTime(item.start),
    description,
    summary: description === null ? null : truncate(description, SUMMARY_LENGTH),
  };
}
