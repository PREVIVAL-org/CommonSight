/**
 * Collects the license texts of all bundled runtime dependencies into dist/licenses/ (R-04).
 * Based on package-lock.json: all packages without the `dev` flag; plus the licenses of bundled assets such as fonts
 * (LICENSE-*.txt files in public/fonts). Packages that ship no license file get the upstream text from licenses/
 * (licenses/<name with / as ->.txt). A shipped package without any license text fails the build: its license may
 * demand the notice (BSD, MIT).
 */
import { mkdir, readdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const lock = JSON.parse(await readFile(join(root, 'package-lock.json'), 'utf8'));

async function licenseText(dir) {
  const files = await readdir(dir).catch(() => []);
  const name = files.find((file) => /^(licen[cs]e|copying)(\.|$)/i.test(file));
  return name === undefined ? null : readFile(join(dir, name), 'utf8');
}

/** The upstream license text kept in the repository for a package that ships none. */
async function overrideText(name) {
  return readFile(join(root, 'licenses', `${name.replace('@', '').replaceAll('/', '-')}.txt`), 'utf8').catch(
    () => null,
  );
}

const sections = [];
const missing = [];
for (const [path, meta] of Object.entries(lock.packages ?? {})) {
  if (path === '' || meta.dev === true || meta.devOptional === true || !path.startsWith('node_modules/'))
    continue;
  const name = path.slice(path.lastIndexOf('node_modules/') + 'node_modules/'.length);
  const text = (await licenseText(join(root, path))) ?? (await overrideText(name));
  if (text === null) missing.push(name);
  sections.push(
    `${'='.repeat(78)}\n${name}@${meta.version} (${meta.license ?? 'license not declared'})\n${'='.repeat(78)}\n${text ?? ''}\n`,
  );
}
if (missing.length > 0) {
  throw new Error(`No license text for ${missing.join(', ')}: add the upstream text as licenses/<name>.txt`);
}

// Bundled assets that are not npm packages, e.g. the Open Sans fonts.
for (const dir of ['fonts']) {
  const files = await readdir(join(root, 'public', dir)).catch(() => []);
  for (const file of files.filter((name) => /^LICENSE-.+\.txt$/.test(name))) {
    const text = await readFile(join(root, 'public', dir, file), 'utf8');
    sections.push(`${'='.repeat(78)}\n${dir}/${file}\n${'='.repeat(78)}\n${text}\n`);
  }
}

const out = join(root, 'dist', 'licenses');
await mkdir(out, { recursive: true });
await writeFile(
  join(out, 'THIRD-PARTY-LICENSES.txt'),
  `License texts of the libraries shipped with CommonSight\n\n${sections.join('\n')}`,
  'utf8',
);
console.log(`${sections.length} license entries written to dist/licenses/THIRD-PARTY-LICENSES.txt`);
