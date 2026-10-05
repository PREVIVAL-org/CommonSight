/**
 * End-to-end tests against the built bundle (vite preview) with fixtures instead of the backend
 * (Architecture 12.4).
 * Run in the official Playwright image (browsers included, same version as @playwright/test):
 *   docker run --rm --ipc=host -v "$PWD":/src -w /src/frontend -u 1000:1000 -e HOME=/tmp \
 *     mcr.microsoft.com/playwright:v1.63.0-noble npx playwright test
 */
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  testMatch: '**/*.spec.ts',
  fullyParallel: true,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:4173',
    trace: 'retain-on-failure',
  },
  webServer: {
    // Fixed IPv4 address: `localhost` resolves to ::1 in the container, but the wait targets 127.0.0.1.
    command: 'npm run build && npx vite preview --host 127.0.0.1 --port 4173 --strictPort',
    url: 'http://127.0.0.1:4173/index.html',
    reuseExistingServer: true,
    timeout: 180_000,
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
