/**
 * Formats instants and numbers via Intl in the browser's language and time zone (I-02 to I-04). What becomes words
 * inside the texts (relative ages, country names) follows the language of the texts, so that a German sentence does
 * not end in English ("Abgerufen: vor 5 Minuten", "München (Deutschland)").
 */
export interface Formats {
  /** Short data timestamp with day, month, hour, minute (I-03); `null` if the instant is missing. */
  dateTime(iso: string | null | undefined): string | null;
  /** Number with at most `maxFractionDigits` fraction digits. */
  number(value: number, maxFractionDigits?: number): string;
  /** Age of an instant relative to `nowMs`, e.g. "vor 5 Minuten" (U-53); `null` without an instant. */
  age(iso: string | null | undefined, nowMs: number): string | null;
  /** Name of a country from its ISO code in the language of the texts, e.g. "Frankreich" for FR. */
  countryName(code: string): string;
}

const MINUTE_MS = 60_000;

function relativeAge(format: Intl.RelativeTimeFormat, ageMs: number): string {
  const minutes = Math.max(0, Math.round(ageMs / MINUTE_MS));
  if (minutes < 60) return format.format(-minutes, 'minute');
  const hours = Math.round(minutes / 60);
  return hours < 48 ? format.format(-hours, 'hour') : format.format(-Math.round(hours / 24), 'day');
}

export interface FormatSettings {
  locales: readonly string[];
  timeZone: string | undefined;
  /** language of the texts, for the words among the formats (relative ages, country names); default: the browser's */
  textLang?: string;
}

function parseInstant(iso: string | null | undefined): Date | null {
  if (iso === null || iso === undefined) return null;
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}

function numberFormatter(locales: string[]): (digits: number) => Intl.NumberFormat {
  const cache = new Map<number, Intl.NumberFormat>();
  return (digits) => {
    let format = cache.get(digits);
    if (format === undefined) {
      format = new Intl.NumberFormat(locales, { maximumFractionDigits: digits });
      cache.set(digits, format);
    }
    return format;
  };
}

/** Short data timestamp (I-03). */
const DATE_TIME_OPTIONS = {
  short: { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' },
} satisfies Record<string, Intl.DateTimeFormatOptions>;

export function createFormats(settings: FormatSettings): Formats {
  const locales = [...settings.locales];
  const zone = settings.timeZone === undefined ? {} : { timeZone: settings.timeZone };
  const dateTimeFormat = (options: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat(locales, { ...options, ...zone });
  const short = dateTimeFormat(DATE_TIME_OPTIONS.short);
  const words = settings.textLang === undefined ? locales : [settings.textLang];
  const relative = new Intl.RelativeTimeFormat(words, { numeric: 'auto' });
  const numberFormat = numberFormatter(locales);
  const regionNames =
    typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames(words, { type: 'region' }) : null;
  return {
    dateTime: (iso) => {
      const date = parseInstant(iso);
      return date === null ? null : short.format(date);
    },
    number: (value, maxFractionDigits = 3) => numberFormat(maxFractionDigits).format(value),
    age: (iso, nowMs) => {
      const date = parseInstant(iso);
      return date === null ? null : relativeAge(relative, nowMs - date.getTime());
    },
    countryName: (code) => regionNames?.of(code) ?? code,
  };
}
