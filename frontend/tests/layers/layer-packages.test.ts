/**
 * Every layer package passes the same checks, without any entry per layer (layers as plugins, L5): name, icon and
 * the texts of its parts exist, a layer on the map brings a renderer, ranks are unique, and its parts work with the
 * snapshots the backend assembles from the recordings (contract/fixtures).
 */
import { describe, expect, it } from 'vitest';
import { LAYER_IDS, layerMeta } from '../../src/contract/master-data';
import type { Country, Item, LayerId, Msg } from '../../src/contract/types';
import {
  isPersistableLayer,
  LAYER_LIST_IDS,
  LIST_VIEWS,
  PANEL_SLOT,
  TILE_SLOTS,
} from '../../src/domain/layer-slots';
import { uiFormat } from '../../src/domain/views/ui-format';
import { layerIcons } from '../../src/generated/layer-icons';
import { layerMapParts } from '../../src/generated/layer-maps';
import { layerUiParts } from '../../src/generated/layer-ui';
import type { LayerUiPart } from '../../src/sdk/ui';
import { layerColorsCss } from '../../src/theme/stylesheets';
import { contractSnapshots } from '../support/fixtures';
import { NOW, t, viewDeps } from '../support/deps';

/** The message keys a user interface part names. */
function textKeys(part: LayerUiPart | undefined): string[] {
  if (part === undefined) return [];
  const messages: (Msg | undefined)[] = [
    part.legend?.title,
    part.tile?.title,
    part.panel?.title,
    part.notice?.open,
    part.notice?.browse,
    part.emptyText,
  ];
  const keys = messages.flatMap((message) => (message === undefined ? [] : [message.key]));
  return part.newEntriesToast === undefined ? keys : [...keys, part.newEntriesToast];
}

const COUNTRIES: readonly Country[] = ['DE', 'AT', 'CH'];

/** The position of the first located item, as the reference point of a tile. */
function firstPosition(items: readonly Item[]): { lon: number; lat: number } | null {
  const first = items.find((item) => item.lat !== undefined && item.lon !== undefined);
  return first?.lon === undefined || first.lat === undefined ? null : { lon: first.lon, lat: first.lat };
}

describe.each(LAYER_IDS.map((layer) => [layer]))('layer package %s', (layer: LayerId) => {
  it('has a name and a lucide icon', () => {
    expect(t.knows(`layer.${layer}.name`)).toBe(true);
    expect(layerIcons[layerMeta(layer).icon]).toBeDefined();
  });

  it('has its color as a variable the host page can override (T-06)', () => {
    expect(layerColorsCss()).toContain(
      `--_layer-${layer}: var(--cs-layer-${layer}, ${layerMeta(layer).color});`,
    );
  });

  it('has a text for every message its parts name', () => {
    const unknown = textKeys(layerUiParts[layer]).filter((key) => !t.knows(key));
    expect(unknown).toEqual([]);
  });

  it('brings a renderer exactly when it is on the map', () => {
    expect(layerMapParts[layer] !== undefined).toBe(layerMeta(layer).onMap);
  });

  it('names known texts in its legend for every country', () => {
    const lines = layerUiParts[layer]?.legend?.lines({ countries: COUNTRIES, snapshot: undefined }) ?? [];
    expect(lines.filter((line) => !t.knows(line.key))).toEqual([]);
  });
});

describe('what the core derives from the layer packages', () => {
  it('shows a tab for each list view that has layers', () => {
    const views = new Set(LAYER_IDS.map((id) => layerMeta(id).view).filter((view) => view !== null));
    expect([...LIST_VIEWS].sort()).toEqual([...views].sort());
  });

  it('saves only active layers of the layer list that have a list view', () => {
    for (const layer of LAYER_IDS) {
      expect(isPersistableLayer(layer)).toBe(
        LAYER_LIST_IDS.includes(layer) && layerMeta(layer).view !== null,
      );
    }
    if (PANEL_SLOT !== null) expect(isPersistableLayer(PANEL_SLOT.layer)).toBe(false);
  });
});

describe('ranks of the layer packages', () => {
  it('are unique for drawing (the region outline has 20) and for the tiles', () => {
    const drawRanks = [20, ...Object.values(layerMapParts).map((part) => part.drawRank)];
    expect(new Set(drawRanks).size).toBe(drawRanks.length);
    const tileRanks = TILE_SLOTS.map((slot) => slot.part.rank);
    expect(new Set(tileRanks).size).toBe(tileRanks.length);
  });
});

describe.each(contractSnapshots().map(({ name, snapshot }) => [name, snapshot]))(
  'parts with the assembled snapshot %s',
  (_name, snapshot) => {
    const { layer, items } = snapshot;
    const context = { layer, layerColor: '#398ed4', labelColor: '#333333', labelHalo: '#ffffff', nowMs: NOW };

    it('draw only items of the snapshot, for their layer', () => {
      const features = layerMapParts[layer]?.renderer.toFeatures(items, context) ?? [];
      const ids = new Set(items.map((item) => item.id));
      // A renderer that silently drew nothing would pass the check below: located items must show up.
      const located = items.some((item) => item.lat !== undefined || item.geometry !== undefined);
      if (layerMapParts[layer] !== undefined && located) expect(features.length).toBeGreaterThan(0);
      expect(
        features.filter(
          (feature) => !ids.has(feature.properties.itemId) || feature.properties.layer !== layer,
        ),
      ).toEqual([]);
    });

    it('fill the tile with known texts', () => {
      const tile = layerUiParts[layer]?.tile;
      if (tile === undefined) return;
      const format = uiFormat(viewDeps());
      const value = tile.value({
        snapshot,
        matched: items,
        reference: firstPosition(items),
        nowMs: NOW,
        format,
      });
      // With data the tile shows a value (a tile that always stayed empty would pass the checks below).
      if (items.length > 0) expect(value).not.toBeNull();
      if (value?.hint !== undefined) expect(t.knows(value.hint.key)).toBe(true);
      if (value?.history !== undefined) expect(t.knows(value.history.label.key)).toBe(true);
    });

    it('fill the legend with known texts', () => {
      const lines = layerUiParts[layer]?.legend?.lines({ countries: COUNTRIES, snapshot }) ?? [];
      expect(lines.filter((line) => !t.knows(line.key))).toEqual([]);
    });
  },
);
