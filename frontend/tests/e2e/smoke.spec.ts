/**
 * End-to-end slice in the browser: shadow DOM, portals, keyboard and focus return, with fixtures instead of the
 * backend (Architecture 12.4, 9.5).
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Locator, Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

const fixtures = join(dirname(fileURLToPath(import.meta.url)), '..', 'fixtures');
const read = (name: string): string => readFileSync(join(fixtures, name), 'utf8');

const SNAPSHOTS: Record<string, string> = {
  'AT/warnings.3f9a1c2e.json': 'warnings-AT.json',
  'AT/weather.aa000001.json': 'weather-AT.json',
  'AT/air.aa000002.json': 'air-AT.json',
  'AT/radiation.aa000003.json': 'radiation-AT.json',
  'global/space.aa000004.json': 'space-global.json',
  'AT/nature.aa000005.json': 'nature-AT.json',
  'AT/traffic.aa000006.json': 'traffic-AT.json',
  'global/news.aa000007.json': 'news-global.json',
};

test.beforeEach(async ({ page }) => {
  await page.route('**/api/status.php**', (route) =>
    route.fulfill({
      contentType: 'application/json',
      body: read('status-AT.json'),
      headers: { ETag: '"e2e"' },
    }),
  );
  await page.route('**/data/v1/**', (route) => {
    const key = new URL(route.request().url()).pathname.split('/data/v1/')[1] ?? '';
    const file = SNAPSHOTS[key];
    return file === undefined
      ? route.fulfill({ status: 404 })
      : route.fulfill({ contentType: 'application/json', body: read(file) });
  });
  await page.route('**/tiles/**', (route) => route.fulfill({ status: 404 }));
  await page.route('**/map/**', (route) => route.fulfill({ status: 404 }));
});

test('renders the element in its shadow root and opens the source sheet', async ({ page }) => {
  await page.goto('/index.html');
  // The header with logo, name and light/dark switch is part of the element (ACCESS-AND-BRANDING B-D1); this page sets
  // no site-name, so the name is CommonSight.
  const element = page.locator('commonsight-map');
  await expect(element.getByRole('heading', { level: 1, name: 'CommonSight' })).toBeVisible();
  await expect(element.getByRole('button', { name: 'Darstellung: Automatisch' })).toBeVisible();
  await expect(page.getByText(/^3 Warnungen in den verbundenen Quellen · unvollständig/)).toBeVisible();
  await expect(
    page.getByText('Die Karte konnte nicht geladen werden. Die Datenlisten bleiben verfügbar.'),
  ).toBeVisible();

  const sourcesButton = page.getByRole('button', { name: 'Datenquellen' });
  await sourcesButton.click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('Datenquellen & Abdeckung')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(sourcesButton).toBeFocused();
});

test('switches views and places, including "Alle Länder"', async ({ page }) => {
  await page.goto('/index.html');
  await page.getByRole('tab', { name: /Warnungen & Ereignisse/ }).click();
  await expect(page.getByRole('searchbox', { name: /Einträge filtern/ })).toBeVisible();
  // Own list with flags (Radix Select): open, then choose the entry.
  const place = page.getByRole('combobox', { name: 'Land / Region' });
  await place.click();
  await expect(page.getByRole('listbox').getByRole('option').first()).toHaveText('Alle Länder');
  await page.getByRole('option', { name: 'Wien' }).click();
  await expect(page.getByText(/^Wien · \d+ regional zugeordnete/)).toBeVisible();
  await place.click();
  await page.getByRole('option', { name: 'Alle Länder' }).click();
  await expect(place).toHaveText('Alle Länder');
});

test('opens the warnings from the map notice with "Anzeigen" (U-23)', async ({ page }) => {
  await page.goto('/index.html');
  await page.getByRole('button', { name: 'Warnungen anzeigen' }).click();
  await expect(page.getByRole('tab', { name: /Warnungen & Ereignisse/ })).toHaveAttribute(
    'aria-selected',
    'true',
  );
});

/** Bounding box of a visible element; fails the test if there is none. */
async function box(locator: Locator): Promise<{ x: number; y: number; width: number; height: number }> {
  const found = await locator.boundingBox();
  if (found === null) throw new Error('element has no bounding box');
  return found;
}

/** "i" on the same line as the country selector, the view tabs below (mobile). */
async function expectToggleBesidePicker(page: Page, toggleLocator: Locator): Promise<void> {
  const picker = await box(page.getByRole('combobox', { name: 'Land / Region' }));
  const toggle = await box(toggleLocator);
  const tabs = await box(page.getByRole('tablist'));
  expect(Math.abs(toggle.y + toggle.height / 2 - (picker.y + picker.height / 2))).toBeLessThan(4);
  expect(tabs.y).toBeGreaterThan(picker.y + picker.height);
}

test('shows the closed map notice as "i" next to the country selector on phones', async ({ page }, info) => {
  await page.goto('/index.html');
  await page.getByRole('button', { name: 'Kartenhinweis schließen' }).click();
  const show = page.getByRole('button', { name: 'Kartenhinweis einblenden' });
  const toolbarToggle = page.locator('commonsight-map').locator('.notice-toggle').getByRole('button');
  const mapButton = page.locator('commonsight-map').locator('.map-notice-show');
  if (info.project.name === 'mobile') {
    await expect(toolbarToggle).toBeVisible();
    await expect(mapButton).toBeHidden();
    await expectToggleBesidePicker(page, toolbarToggle);
  } else {
    await expect(toolbarToggle).toBeHidden();
    await expect(mapButton).toBeVisible();
  }
  await show.locator('visible=true').first().click();
  await expect(page.getByRole('button', { name: 'Kartenhinweis schließen' })).toBeVisible();
});

/** Gap between the thumb and the left and right edge of a switch, in pixels. */
async function thumbGaps(page: Page, name: RegExp): Promise<{ left: number; right: number }> {
  const switchLocator = page.getByRole('switch', { name });
  const track = await box(switchLocator);
  const thumb = await box(switchLocator.locator('.switch-thumb'));
  return { left: thumb.x - track.x, right: track.x + track.width - (thumb.x + thumb.width) };
}

test('places the switch thumb at the left edge when off and at the right edge when on', async ({ page }) => {
  await page.goto('/index.html');
  const on = await thumbGaps(page, /Amtliche Warnungen/);
  const off = await thumbGaps(page, /Strahlung/);
  expect(on.right).toBeCloseTo(2, 0);
  expect(off.left).toBeCloseTo(2, 0);
});

test('closes the detail sheet step by step with the browser back button', async ({ page }) => {
  await page.goto('/index.html');
  await page.getByRole('button', { name: 'Datenquellen' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('Datenquellen & Abdeckung')).toBeVisible();
  await dialog.getByRole('button', { name: 'Daten ansehen' }).first().click();
  await expect(dialog.getByText('Datenquellen & Abdeckung')).toBeHidden();
  await page.goBack();
  await expect(dialog.getByText('Datenquellen & Abdeckung')).toBeVisible();
  await page.goBack();
  await expect(dialog).toBeHidden();
  await expect(page).toHaveURL(/\/index\.html$/);
});

test('keeps the map height independent of the window height', async ({ page }) => {
  await page.goto('/index.html');
  const map = page.locator('commonsight-map').locator('.map-stage');
  const before = await map.boundingBox();
  expect(before?.height ?? 0).toBeGreaterThan(300);
  await page.setViewportSize({ width: page.viewportSize()?.width ?? 1280, height: 1400 });
  const after = await map.boundingBox();
  expect(Math.round(after?.height ?? 0)).toBe(Math.round(before?.height ?? -1));
});
