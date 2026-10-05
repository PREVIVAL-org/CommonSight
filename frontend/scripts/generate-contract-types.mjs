/**
 * Generates src/contract/generated.ts from contract/schema/snapshot.schema.json and status.schema.json
 * (Architecture 3.1, V11). The output is never maintained by hand.
 *
 * json-schema-to-typescript does not understand two Draft 2020-12 constructs the way the schema means them.
 * Before generating, the schemas are therefore adjusted in a working copy (node_modules/.cache):
 * - `prefixItems` + `items: false` becomes the equivalent tuple notation `items: [...]`,
 * - sibling keys next to `$ref` (descriptions only) are dropped so that identical types are not emitted twice.
 */
import { mkdir, readdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { compile } from 'json-schema-to-typescript';

const here = dirname(fileURLToPath(import.meta.url));
const schemaDir = join(here, '..', '..', 'contract', 'schema');
const workDir = join(here, '..', 'node_modules', '.cache', 'contract-schema');
const target = join(here, '..', 'src', 'contract', 'generated.ts');

function normalize(node) {
  if (Array.isArray(node)) return node.map(normalize);
  if (node === null || typeof node !== 'object') return node;
  if (typeof node.$ref === 'string') return { $ref: node.$ref };
  const out = {};
  for (const [key, value] of Object.entries(node)) {
    if (key === 'prefixItems') continue;
    if (key === 'items' && Array.isArray(node.prefixItems)) continue;
    out[key] = normalize(value);
  }
  if (Array.isArray(node.prefixItems)) {
    out.items = node.prefixItems.map(normalize);
    if (node.items === false) out.additionalItems = false;
  }
  return out;
}

async function prepareWorkCopy() {
  await mkdir(workDir, { recursive: true });
  for (const file of await readdir(schemaDir)) {
    if (!file.endsWith('.schema.json')) continue;
    const schema = JSON.parse(await readFile(join(schemaDir, file), 'utf8'));
    delete schema.$id;
    await writeFile(join(workDir, file), JSON.stringify(normalize(schema), null, 2), 'utf8');
  }
}

const root = {
  title: 'ContractRoot',
  description: 'Collective root only for generation; refers to the two entry schemas.',
  type: 'object',
  properties: {
    snapshot: { $ref: 'snapshot.schema.json' },
    status: { $ref: 'status.schema.json' },
  },
  required: ['snapshot', 'status'],
  additionalProperties: false,
};

const banner = [
  '/* eslint-disable */',
  '/**',
  ' * Types of the contract, generated from contract/schema (json-schema-to-typescript).',
  ' * DO NOT EDIT BY HAND: npm run generate',
  ' */',
].join('\n');

await prepareWorkCopy();
const source = await compile(root, 'ContractRoot', {
  cwd: workDir,
  bannerComment: banner,
  additionalProperties: false,
  declareExternallyReferenced: true,
  strictIndexSignatures: true,
  unreachableDefinitions: false,
  maxItems: -1,
  format: true,
  style: { singleQuote: true, printWidth: 110 },
});

await mkdir(dirname(target), { recursive: true });
await writeFile(target, source, 'utf8');
console.log(`contract types written to ${target}`);
