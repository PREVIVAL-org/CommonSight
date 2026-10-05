/**
 * Names the contract types generated from the schema and derives the frontend's base types from them.
 */
import type { Item, LayerStatus, Snapshot, StatusResponse } from './generated';

export type {
  Assessment,
  EarthquakeItem,
  Fact,
  Geometry,
  IndexItem,
  Item,
  LayerStatus,
  MeasurementItem,
  ModelValueItem,
  Msg,
  NewsItem,
  Position,
  RadiationStats,
  Snapshot,
  SpaceStats,
  StatusResponse,
  TrafficNoticeItem,
  WarningItem,
  WarningSection,
} from './generated';

export type LayerId = Snapshot['layer'];
export type Scope = Snapshot['scope'];
export type Country = StatusResponse['scope'];
export type LayerStatusValue = LayerStatus['status'];
export type Level = Extract<Item, { kind: 'measurement' }>['assessment']['level'];
export type Severity = Extract<Item, { kind: 'warning' }>['severity'];
export type NewsCategory = Extract<Item, { kind: 'news' }>['category'];
export type NewsFeed = Extract<Item, { kind: 'news' }>['feed'];
