/**
 * Reads the browser's language and time zone for formatting (I-02).
 */

export interface BrowserLocale {
  locales: readonly string[];
  timeZone: string | undefined;
}

export function readBrowserLocale(nav: Navigator): BrowserLocale {
  const locales = nav.languages.length > 0 ? nav.languages : [nav.language || 'de'];
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  return { locales, timeZone };
}
