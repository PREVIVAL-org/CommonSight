/**
 * Actions that change the selection: country, region, view, layers, lists and filters (U-10 to U-34, K-09).
 */
import type { MapCamera } from '../domain/map-camera';
import { placeKey, roundCamera } from '../domain/map-camera';
import { layerMeta } from '../contract/master-data';
import type { LayerId } from '../contract/types';
import type { CountryChoice } from '../domain/country-choice';
import { opensSheetOnActivate, toggleLayer } from '../domain/layer-activation';
import { decodePlace } from '../domain/place-choice';
import type { NewsTopic, ViewId } from '../domain/selection';
import { LIST_PAGE_SIZE } from './initial-state';
import type { AppStore } from './patch';
import { patch } from './patch';
import { layerStatusOf } from './selectors/data-selectors';

export interface SelectionActions {
  setCountry(country: CountryChoice): void;
  setRegion(regionId: string | null): void;
  /** Country and region from the combined selector (value as per encodePlace); invalid values are ignored. */
  choosePlace(value: string): void;
  setView(view: ViewId): void;
  toggleLayer(layer: LayerId): void;
  setListLayer(view: 'measurements' | 'events', layer: LayerId): void;
  setQuery(query: string): void;
  showMore(): void;
  /** More entries in the section "Grenzgebiet", without moving the main list. */
  showMoreBorder(): void;
  setTableMode(table: boolean): void;
  setNewsTopic(topic: NewsTopic): void;
  setMapNoticeHidden(hidden: boolean): void;
  /** Map attribution ("i") collapsed; persists across reloads (U-60). */
  setAttributionHidden(hidden: boolean): void;
  /** Switch "Grenzgebiet": border zone beyond DACH on or off (ADR 0038). */
  setBorderZone(on: boolean): void;
  /** The map came to rest: remember its section for the current place (U-60). */
  setMapView(camera: MapCamera): void;
  showOnMap(layer: LayerId, itemId: string): void;
  /** Switches to the list view of the layer with its entries (e.g. the warnings from the map notice). */
  openEntries(layer: LayerId): void;
}

/** A country switch resets region, detail sheet and map focus (U-11). */
function setCountry(store: AppStore, country: CountryChoice): void {
  if (store.getState().selection.country === country) return;
  patch(store, 'selection', {
    country,
    regionId: null,
    query: '',
    listLimit: LIST_PAGE_SIZE,
    borderLimit: LIST_PAGE_SIZE,
  });
  patch(store, 'ui', { sheet: null });
  patch(store, 'map', { focus: null, outline: { state: 'idle' } });
}

/** A different country first resets everything (U-11), then the selected region applies (U-13). */
function choosePlace(store: AppStore, value: string): void {
  const choice = decodePlace(value);
  if (choice === null) return;
  setCountry(store, choice.country);
  patch(store, 'selection', {
    regionId: choice.regionId,
    listLimit: LIST_PAGE_SIZE,
    borderLimit: LIST_PAGE_SIZE,
  });
}

function toggle(store: AppStore, layer: LayerId): void {
  const state = store.getState();
  const activating = !state.selection.activeLayers.includes(layer);
  patch(store, 'selection', { activeLayers: toggleLayer(state.selection.activeLayers, layer) });
  if (activating && opensSheetOnActivate(layer, layerStatusOf(state, layer)?.status)) {
    patch(store, 'ui', { sheet: { type: 'layer', layer } });
  }
}

function setListLayer(store: AppStore, view: 'measurements' | 'events', layer: LayerId): void {
  patch(store, 'selection', (current) => ({
    listLayers: { ...current.listLayers, [view]: layer },
    query: '',
    listLimit: LIST_PAGE_SIZE,
    borderLimit: LIST_PAGE_SIZE,
  }));
}

/** "Auf Karte anzeigen" (show on map): activate the layer, switch to the map view, request focus (K-09). */
function showOnMap(store: AppStore, layer: LayerId, itemId: string): void {
  const state = store.getState();
  const { activeLayers } = state.selection;
  const activate = layerMeta(layer).onMap && !activeLayers.includes(layer);
  patch(store, 'selection', {
    view: 'overview',
    activeLayers: activate ? [...activeLayers, layer] : activeLayers,
  });
  patch(store, 'ui', { sheet: null });
  patch(store, 'map', { focus: { seq: (state.map.focus?.seq ?? 0) + 1, type: 'item', layer, itemId } });
}

/** One page more of the main list or of the section "Grenzgebiet". */
function nextPage(store: AppStore, limit: 'listLimit' | 'borderLimit'): void {
  patch(store, 'selection', (current) => ({ [limit]: current[limit] + LIST_PAGE_SIZE }));
}

/** The list view of the layer with its entries; a layer without a list view stays where it is. */
function openEntries(store: AppStore, layer: LayerId): void {
  const view = layerMeta(layer).view;
  if (view === null) return;
  setListLayer(store, view, layer);
  patch(store, 'selection', { view });
}

export function createSelectionActions(store: AppStore): SelectionActions {
  return {
    setCountry: (country) => setCountry(store, country),
    setRegion: (regionId) =>
      patch(store, 'selection', { regionId, listLimit: LIST_PAGE_SIZE, borderLimit: LIST_PAGE_SIZE }),
    choosePlace: (value) => choosePlace(store, value),
    setView: (view) =>
      patch(store, 'selection', { view, query: '', listLimit: LIST_PAGE_SIZE, borderLimit: LIST_PAGE_SIZE }),
    toggleLayer: (layer) => toggle(store, layer),
    setListLayer: (view, layer) => setListLayer(store, view, layer),
    setQuery: (query) =>
      patch(store, 'selection', { query, listLimit: LIST_PAGE_SIZE, borderLimit: LIST_PAGE_SIZE }),
    showMore: () => nextPage(store, 'listLimit'),
    showMoreBorder: () => nextPage(store, 'borderLimit'),
    setTableMode: (tableMode) => patch(store, 'selection', { tableMode }),
    setNewsTopic: (newsTopic) => patch(store, 'selection', { newsTopic }),
    setMapNoticeHidden: (mapNoticeHidden) => patch(store, 'selection', { mapNoticeHidden }),
    setAttributionHidden: (attributionHidden) => patch(store, 'selection', { attributionHidden }),
    setBorderZone: (borderZone) => patch(store, 'selection', { borderZone, borderLimit: LIST_PAGE_SIZE }),
    setMapView: (camera) =>
      patch(store, 'selection', (current) => ({
        mapView: { ...roundCamera(camera), place: placeKey(current.country, current.regionId) },
      })),
    showOnMap: (layer, itemId) => showOnMap(store, layer, itemId),
    openEntries: (layer) => openEntries(store, layer),
  };
}
