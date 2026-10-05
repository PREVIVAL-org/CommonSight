/**
 * Reports the compressed sizes of the bundle (budget Architecture 12.2) and, if dist-analyze/ exists,
 * the largest modules per chunk (raw size of the unminified regions).
 */
import { readdir, readFile, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

const root = fileURLToPath(new URL('..', import.meta.url));

async function listJs(dir) {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...(await listJs(path)));
    else if (entry.name.endsWith('.js')) out.push(path);
  }
  return out;
}

async function exists(path) {
  return stat(path).then(
    () => true,
    () => false,
  );
}

async function reportSizes() {
  let total = 0;
  for (const file of await listJs(join(root, 'dist'))) {
    const gz = gzipSync(await readFile(file)).length;
    total += gz;
    console.log(`${(gz / 1024).toFixed(1).padStart(8)} KB gz  ${file.slice(root.length)}`);
  }
  console.log(`${(total / 1024).toFixed(1).padStart(8)} KB gz  gesamt`);
}

async function reportModules() {
  const dir = join(root, 'dist-analyze');
  if (!(await exists(dir))) return;
  for (const file of await listJs(dir)) {
    const text = await readFile(file, 'utf8');
    const sizes = new Map();
    const parts = text.split(/^\/\/#region /m).slice(1);
    for (const part of parts) {
      const name = part
        .slice(0, part.indexOf('\n'))
        .replace(/node_modules\/(\.pnpm\/)?/, '')
        .split('/')
        .slice(0, 2)
        .join('/');
      sizes.set(name, (sizes.get(name) ?? 0) + part.length);
    }
    console.log(`\n${file.slice(root.length)}`);
    [...sizes.entries()]
      .sort((a, b) => b[1] - a[1])
      .slice(0, 15)
      .forEach(([name, size]) => console.log(`${(size / 1024).toFixed(1).padStart(8)} KB  ${name}`));
  }
}

await reportSizes();
await reportModules();
