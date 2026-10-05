/**
 * Creates fixed texts and formats for tests (German, Europe/Berlin), independent of the language and time zone
 * of the test environment.
 */
import type { ViewDeps } from '../../src/domain/views/view-deps';
import { catalogsFor } from '../../src/i18n/catalogs';
import type { Formats } from '../../src/i18n/formats';
import { createFormats } from '../../src/i18n/formats';
import type { Translator } from '../../src/i18n/translator';
import { createTranslator } from '../../src/i18n/translator';

export const NOW = Date.parse('2026-09-28T12:00:00Z');

export const formats: Formats = createFormats({ locales: ['de-DE'], timeZone: 'Europe/Berlin' });

export const t: Translator = createTranslator({
  ...catalogsFor('de'),
  locale: 'de-DE',
  formatNumber: (value) => formats.number(value),
});

export function viewDeps(nowMs: number = NOW): ViewDeps {
  return { t, format: formats, nowMs };
}
