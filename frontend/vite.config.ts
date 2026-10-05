/**
 * Builds the custom element in library mode: ES module `commonsight.js` with hashed chunks (Architecture 9.7).
 * `vite build --mode analyze` writes an unminified version to dist-analyze/ for the size analysis
 * (npm run size).
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const contractDir = fileURLToPath(new URL('../contract', import.meta.url));
/** Layer packages (frontend parts) and the plugin API they use (layers as plugins, L3). */
const pluginsDir = fileURLToPath(new URL('../plugins', import.meta.url));
const sdkDir = fileURLToPath(new URL('./src/sdk', import.meta.url));
/** Version from the file VERSION in the repository root, maintained by hand; built into the bundle. */
const appVersion = readFileSync(new URL('../VERSION', import.meta.url), 'utf8').trim();

export default defineConfig(({ command, mode }) => {
  const analyze = mode === 'analyze';
  return {
    plugins: [react()],
    // The bundle lives under <base-url>/app/<version>/; resolve asset URLs (e.g. the MapLibre worker)
    // relative to the module.
    base: './',
    resolve: {
      alias: { '@contract': contractDir, '@plugins': pluginsDir, '@sdk': sdkDir },
    },
    // In library mode Vite does not replace process.env.NODE_ENV; React needs the value for the production build.
    define: {
      __CS_VERSION__: JSON.stringify(appVersion),
      ...(command === 'build' ? { 'process.env.NODE_ENV': JSON.stringify('production') } : {}),
    },
    server: {
      fs: { allow: ['..'] },
    },
    // MapLibre 6 starts its worker as a module worker.
    worker: { format: 'es' as const },
    build: {
      target: 'es2022',
      outDir: analyze ? 'dist-analyze' : 'dist',
      copyPublicDir: !analyze,
      sourcemap: false,
      chunkSizeWarningLimit: 1200,
      lib: {
        entry: fileURLToPath(new URL('./src/element.ts', import.meta.url)),
        formats: ['es'],
        fileName: () => 'commonsight.js',
      },
      rollupOptions: {
        output: {
          chunkFileNames: 'chunks/[name]-[hash].js',
          assetFileNames: 'assets/[name]-[hash][extname]',
          // In library mode Vite does not minify ES output otherwise; the bundle is delivered directly.
          minify: !analyze,
        },
      },
    },
  };
});
