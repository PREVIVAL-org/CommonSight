/**
 * Cross-check of the contract (Architecture 3.1): all fixtures match the schema, every Msg key has a text,
 * and every snapshot can be fully converted into view models, tooltips and map features.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import Ajv2020 from 'ajv/dist/2020';
import { describe, expect, it } from 'vitest';
import { contractMessagesDe } from '../../src/contract/messages';
import type { Snapshot } from '../../src/contract/types';
import { toItemView } from '../../src/domain/views/item-view';
import { featureCollection } from '../../src/map/layers/feature-model';
import { layerRenderers } from '../../src/map/layers/renderers';
import { tooltipHtml } from '../../src/map/tooltip';
import { contractSnapshots, OWN_FIXTURES, ownSnapshots, readJsonDir, SCHEMA_DIR } from '../support/fixtures';
import { NOW, viewDeps } from '../support/deps';

function createValidator(): Ajv2020 {
  const ajv = new Ajv2020({ allErrors: true, strict: false });
  for (const file of readdirSync(SCHEMA_DIR).filter((name) => name.endsWith('.schema.json'))) {
    ajv.addSchema(JSON.parse(readFileSync(join(SCHEMA_DIR, file), 'utf8')) as object);
  }
  return ajv;
}

const SCHEMA_BASE = 'https://commonsight.org/schema/v1/';

/** Collects all Msg keys (objects with `key`) from a JSON value. */
export function msgKeys(value: unknown, into: Set<string> = new Set()): Set<string> {
  if (Array.isArray(value)) value.forEach((entry) => msgKeys(entry, into));
  else if (typeof value === 'object' && value !== null) {
    const record = value as Record<string, unknown>;
    if (typeof record.key === 'string') into.add(record.key);
    Object.values(record).forEach((entry) => msgKeys(entry, into));
  }
  return into;
}

const allSnapshots = [
  ...ownSnapshots(),
  ...contractSnapshots().map((entry) => ({ ...entry, name: `contract/${entry.name}` })),
];

describe('contract fixtures', () => {
  const ajv = createValidator();

  it.each(readJsonDir(OWN_FIXTURES).map((entry) => [entry.name, entry.data] as const))(
    '%s matches the schema',
    (name, data) => {
      const schema = name.startsWith('status-') ? 'status.schema.json' : 'snapshot.schema.json';
      const validate = ajv.getSchema(`${SCHEMA_BASE}${schema}`);
      expect(validate).toBeDefined();
      const valid = validate?.(data);
      expect(validate?.errors ?? [], name).toEqual([]);
      expect(valid).toBe(true);
    },
  );

  it.each(contractSnapshots().map((entry) => [entry.name, entry.snapshot] as const))(
    'contract/%s matches the schema',
    (_name, data) => {
      const validate = ajv.getSchema(`${SCHEMA_BASE}snapshot.schema.json`);
      expect(validate?.(data)).toBe(true);
    },
  );

  it.each(allSnapshots.map((entry) => [entry.name, entry.snapshot] as const))(
    '%s uses only known message keys',
    (_name, data) => {
      const missing = [...msgKeys(data)].filter((key) => contractMessagesDe[key] === undefined);
      expect(missing).toEqual([]);
    },
  );

  it.each(allSnapshots.map((entry) => [entry.name, entry.snapshot] as const))(
    '%s renders every item as view, tooltip and feature',
    (_name, data) => {
      const snapshot = data as Snapshot;
      const context = { layer: snapshot.layer, source: snapshot.source };
      for (const item of snapshot.items) {
        const view = toItemView(item, context, viewDeps());
        expect(view.kind).toBe(item.kind);
        expect(tooltipHtml(view)).toContain('cs-tooltip-title');
      }
      const renderer = layerRenderers[snapshot.layer];
      if (renderer !== undefined) {
        const renderContext = {
          layer: snapshot.layer,
          layerColor: '#398ed4',
          labelColor: '#333',
          labelHalo: '#fff',
          nowMs: NOW,
        };
        const features = featureCollection(renderer.toFeatures(snapshot.items, renderContext));
        expect(features.features.every((feature) => feature.properties.layer === snapshot.layer)).toBe(true);
      }
    },
  );
});
