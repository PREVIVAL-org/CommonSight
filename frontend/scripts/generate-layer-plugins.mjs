/**
 * Generates the registries of the layer packages (layers as plugins, L3, L-D4): from the layer registry
 * (contract/catalog/layers.json, itself generated from the packages) and the frontend parts in
 * plugins/layers/<id>/frontend/:
 * - src/generated/layer-maps.ts: the map parts (map.ts), imported by the map chunk,
 * - src/generated/layer-ui.ts: the user interface parts (ui.ts), in the main bundle,
 * - src/generated/layer-icons.ts: exactly the lucide icons the layers name (L-D8).
 * The output is never maintained by hand; an unknown icon or a layer without its package fails the build.
 */
import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..', '..');
const target = join(here, '..', 'src', 'generated');
const lucideIcons = join(here, '..', 'node_modules', 'lucide-react', 'dist', 'esm', 'icons');

const banner = [
  '/* eslint-disable */',
  '/**',
  ' * Generated from the layer packages by scripts/generate-layer-plugins.mjs.',
  ' * DO NOT EDIT BY HAND: npm run generate',
  ' */',
].join('\n');

const layers = JSON.parse(await readFile(join(root, 'contract', 'catalog', 'layers.json'), 'utf8'));
const pascal = (name) => name.replace(/(^|-)([a-z0-9])/g, (_, __, c) => c.toUpperCase());
/** A valid identifier for every layer id (e.g. `default` or `3d-view` would not be one by themselves). */
const variable = (id) => `layer${pascal(id)}`;

function registry(file, typeImport, typeName, exportName) {
  const present = layers.filter((layer) =>
    existsSync(join(root, 'plugins', 'layers', layer.id, 'frontend', `${file}.ts`)),
  );
  const imports = present.map(
    (layer) =>
      `import { ${file} as ${variable(layer.id)} } from '@plugins/layers/${layer.id}/frontend/${file}';`,
  );
  const entries = present.map((layer) => `  '${layer.id}': ${variable(layer.id)},`);
  return [
    banner,
    `import type { ${typeName} } from '${typeImport}';`,
    ...imports,
    '',
    `export const ${exportName}: Readonly<Record<string, ${typeName}>> = {`,
    ...entries,
    '};',
    '',
  ].join('\n');
}

function icons() {
  const names = [...new Set(layers.map((layer) => layer.icon))].sort();
  for (const name of names) {
    if (!existsSync(join(lucideIcons, `${name}.mjs`))) {
      throw new Error(`Layer icon "${name}" is not a lucide icon (L-D8)`);
    }
  }
  return [
    banner,
    "import type { LucideIcon } from 'lucide-react';",
    `import { ${names.map(pascal).join(', ')} } from 'lucide-react';`,
    '',
    '/** By the icon name the layers give in their description. */',
    'export const layerIcons: Readonly<Record<string, LucideIcon>> = {',
    ...names.map((name) => `  '${name}': ${pascal(name)},`),
    '};',
    '',
  ].join('\n');
}

for (const layer of layers) {
  if (!existsSync(join(root, 'plugins', 'layers', layer.id, 'plugin.php'))) {
    throw new Error(`Layer ${layer.id} of the registry has no package in plugins/layers/`);
  }
  // A layer on the map without its renderer would silently draw nothing (e.g. a misnamed file).
  if (layer.onMap && !existsSync(join(root, 'plugins', 'layers', layer.id, 'frontend', 'map.ts'))) {
    throw new Error(`Layer ${layer.id} is on the map but has no plugins/layers/${layer.id}/frontend/map.ts`);
  }
}
await mkdir(target, { recursive: true });
await writeFile(
  join(target, 'layer-maps.ts'),
  registry('map', '../sdk/map', 'LayerMapPart', 'layerMapParts'),
);
await writeFile(join(target, 'layer-ui.ts'), registry('ui', '../sdk/ui', 'LayerUiPart', 'layerUiParts'));
await writeFile(join(target, 'layer-icons.ts'), icons());
console.log(`layer registries written to ${target}`);
