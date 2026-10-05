/**
 * Test configuration: domain logic without browser APIs (environment node); UI tests set jsdom via a file comment.
 */
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [react()],
  // Fixed value instead of the file VERSION, so that tests do not change with every version.
  define: { __CS_VERSION__: JSON.stringify('0.0.0-test') },
  resolve: {
    alias: {
      '@contract': fileURLToPath(new URL('../contract', import.meta.url)),
      '@plugins': fileURLToPath(new URL('../plugins', import.meta.url)),
      '@sdk': fileURLToPath(new URL('./src/sdk', import.meta.url)),
    },
  },
  test: {
    environment: 'node',
    // The tests of the layer packages run with the core's (layers as plugins, L5).
    include: ['tests/**/*.test.{ts,tsx}', '../plugins/layers/*/frontend/**/*.test.ts'],
    exclude: ['tests/e2e/**', 'node_modules/**'],
    setupFiles: ['tests/setup.ts'],
    restoreMocks: true,
  },
});
