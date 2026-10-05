/**
 * Items beyond the border of the selection (ADR 0038): the border zone beyond DACH, with a single country the other
 * two DACH countries, and with a region also the other regions of its country, each within the border radius (vicinityKm in config.php, 200 km by default) of the selected
 * country or region (`near`, computed by the fetcher).
 * The same rule for the section "Grenzgebiet" of the lists and for the map. Memoized per snapshot, so that results
 * stay the same between calls.
 */
import { COUNTRIES, layerMeta } from '../../contract/master-data';
import type { Country, Item, LayerId, Snapshot } from '../../contract/types';
import { nearSelection } from '../../domain/border-filter';
import type { CountryChoice } from '../../domain/country-choice';
import { countriesOf } from '../../domain/country-choice';
import type { SnapshotKey } from '../../domain/snapshot-key';
import type { ItemView } from '../../domain/views/item-view';
import { toItemView } from '../../domain/views/item-view';
import type { ViewDeps } from '../../domain/views/view-deps';
import type { AppState } from '../app-state';
import { memoizeByKey } from '../memo';
import { borderSnapshotOf } from './data-selectors';

/** Same shape as ViewIndex of item-selectors (not imported, that module uses this one). */
type ViewIndex = ReadonlyMap<string, ItemView>;

export interface BorderSelectors {
  /** Items near the selection: the own country outside the region, the DACH neighbours, then the border zone. */
  near(state: AppState, layer: LayerId): Item[];
  /** View models of all these items, by ID; items of a DACH neighbour carry its country in the title. */
  index(state: AppState, layer: LayerId, deps: ViewDeps): ViewIndex;
}

const NO_ITEMS: Item[] = [];
const NO_INDEX: ViewIndex = new Map();

/** Country of the selected region, null without a region. */
function regionCountry(state: AppState): Country | null {
  const regionId = state.selection.regionId;
  return regionId === null ? null : (COUNTRIES.find((code) => regionId.startsWith(`${code}-`)) ?? null);
}

/**
 * The snapshots of the DACH countries outside the selection: without a region those not selected (none with "Alle"),
 * with a region the countries other than the region's. None for country-independent layers.
 */
function neighbourSnapshots(state: AppState, layer: LayerId): [Snapshot | undefined, Snapshot | undefined] {
  if (layerMeta(layer).scope !== 'country') return [undefined, undefined];
  const own = regionCountry(state);
  const selected = own === null ? countriesOf(state.selection.country) : [own];
  const others = COUNTRIES.filter((country) => !selected.includes(country));
  const snapshotOfCountry = (country: Country | undefined): Snapshot | undefined =>
    country === undefined ? undefined : state.data.snapshots[`${country}/${layer}` as SnapshotKey]?.snapshot;
  return [snapshotOfCountry(others[0]), snapshotOfCountry(others[1])];
}

/** With a region selected, the snapshot of its country: its other regions belong to the border area. */
function ownSnapshot(state: AppState, layer: LayerId): Snapshot | undefined {
  const country = regionCountry(state);
  if (country === null || layerMeta(layer).scope !== 'country') return undefined;
  return state.data.snapshots[`${country}/${layer}` as SnapshotKey]?.snapshot;
}

/** Items of the region's own country outside the region and within the border radius of it. */
function outsideRegion(_key: string, snapshot: Snapshot | undefined, regionId: string | null): Item[] {
  if (snapshot === undefined || regionId === null) return NO_ITEMS;
  return snapshot.items.filter(
    (item) => !item.regionIds.includes(regionId) && (item.near?.regionIds ?? []).includes(regionId),
  );
}

/** @param own the selected country itself: its items keep their title without the country name */
function buildIndex(_key: string, snapshot: Snapshot | undefined, deps: ViewDeps, own: boolean): ViewIndex {
  if (snapshot === undefined) return NO_INDEX;
  const neighbour =
    !own && (COUNTRIES as readonly string[]).includes(snapshot.scope) ? snapshot.scope : undefined;
  const context = {
    layer: snapshot.layer,
    source: snapshot.source,
    ...(neighbour === undefined ? {} : { country: neighbour }),
  };
  return new Map(snapshot.items.map((item) => [item.id, toItemView(item, context, deps)]));
}

const keyOf = (snapshot: Snapshot | undefined, layer: LayerId): string =>
  snapshot === undefined ? `none/${layer}` : `${snapshot.scope}/${layer}`;

function joinItems(_key: string, first: Item[], second: Item[]): Item[] {
  if (first.length === 0) return second;
  return second.length === 0 ? first : [...first, ...second];
}

function joinIndexes(_key: string, first: ViewIndex, second: ViewIndex): ViewIndex {
  if (first.size === 0) return second;
  return second.size === 0 ? first : new Map([...first, ...second]);
}

// eslint-disable-next-line max-lines-per-function -- Only collects memoized steps; each is one expression.
export function createBorderSelectors(): BorderSelectors {
  const nearIn = memoizeByKey(
    (_key: string, snapshot: Snapshot | undefined, choice: CountryChoice, regionId: string | null) =>
      snapshot === undefined ? NO_ITEMS : nearSelection(snapshot.items, countriesOf(choice), regionId),
  );
  const ownIn = memoizeByKey(outsideRegion);
  const join = memoizeByKey(joinItems);
  const index = memoizeByKey(buildIndex);
  const joinIndex = memoizeByKey(joinIndexes);
  const near = (state: AppState, layer: LayerId, snapshot: Snapshot | undefined): Item[] =>
    nearIn(keyOf(snapshot, layer), snapshot, state.selection.country, state.selection.regionId);
  return {
    near: (state, layer) => {
      const [first, second] = neighbourSnapshots(state, layer);
      const own = ownSnapshot(state, layer);
      const neighbours = join(`neighbours/${layer}`, near(state, layer, first), near(state, layer, second));
      const dach = join(`dach/${layer}`, ownIn(keyOf(own, layer), own, state.selection.regionId), neighbours);
      return join(`all/${layer}`, dach, near(state, layer, borderSnapshotOf(state, layer)));
    },
    index: (state, layer, deps) => {
      const [first, second] = neighbourSnapshots(state, layer);
      const own = ownSnapshot(state, layer);
      const border = borderSnapshotOf(state, layer);
      const neighbours = joinIndex(
        `neighbours/${layer}`,
        index(keyOf(first, layer), first, deps, false),
        index(keyOf(second, layer), second, deps, false),
      );
      const dach = joinIndex(`dach/${layer}`, index(`own:${keyOf(own, layer)}`, own, deps, true), neighbours);
      return joinIndex(`all/${layer}`, dach, index(keyOf(border, layer), border, deps, false));
    },
  };
}
