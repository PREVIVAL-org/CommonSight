/**
 * Translation and formatting in language and time zone (I-01 to I-04).
 */
import { describe, expect, it } from 'vitest';
import { contractMessagesDe } from '../../src/contract/messages';
import { createFormats } from '../../src/i18n/formats';
import { formats, t } from '../support/deps';

describe('translator', () => {
  it('interpolates parameters and formats numbers in the locale', () => {
    expect(t.msg({ key: 'layer.radiation.note', params: { elevated: 0.3, high: 1 } })).toContain(
      'orange ab 0,3 µSv/h, rot ab 1 µSv/h',
    );
    expect(t.ui('list.more', { count: 1200 })).toBe('Weitere Einträge anzeigen (1.200)');
  });

  it('picks plural forms', () => {
    expect(t.ui('list.count', { count: 1 })).toBe('1 Eintrag');
    expect(t.ui('list.count', { count: 2 })).toBe('2 Einträge');
  });

  it('shows the key for unknown messages and reports whether a key exists', () => {
    expect(t.msg({ key: 'issue.neverDefined' })).toBe('issue.neverDefined');
    expect(t.knows('issue.sourceFailed')).toBe(true);
    expect(t.knows('issue.neverDefined')).toBe(false);
  });

  it('names layers, also one without a text of its own (layers as plugins, L1)', () => {
    expect(t.layer('water')).toBe('Wasserpegel');
    expect(t.layer('ohne-text')).toBe('ohne-text');
    expect(t.layer('constructor')).toBe('constructor');
  });

  it('the contract catalogue is non-empty', () => {
    expect(Object.keys(contractMessagesDe).length).toBeGreaterThan(50);
  });
});

describe('formats', () => {
  it('formats the data time with day, month, hour and minute (I-03)', () => {
    expect(formats.dateTime('2026-09-28T10:15:00Z')).toBe('28.09., 12:15');
    expect(formats.dateTime(null)).toBeNull();
    expect(formats.dateTime('kaputt')).toBeNull();
  });

  it('converts UTC into the browser time zone only for display (I-02)', () => {
    const london = createFormats({ locales: ['en-GB'], timeZone: 'Europe/London' });
    expect(london.dateTime('2026-09-28T10:15:00Z')).toBe('28/09, 11:15');
    const winter = formats.dateTime('2026-12-28T10:15:00Z');
    expect(winter).toBe('28.12., 11:15');
  });

  it('writes dates in the browser format, but words in the language of the texts', () => {
    const english = createFormats({ locales: ['en-US'], timeZone: 'Europe/Berlin', textLang: 'de' });
    const now = Date.parse('2026-09-28T12:00:00Z');
    expect(english.dateTime('2026-09-28T10:15:00Z')).toBe('09/28, 12:15 PM');
    expect(english.age('2026-09-28T11:55:00Z', now)).toBe('vor 5 Minuten');
    expect(english.countryName('DE')).toBe('Deutschland');
  });

  it('formats numbers and ages', () => {
    expect(formats.number(0.12345)).toBe('0,123');
    expect(formats.number(12.34, 1)).toBe('12,3');
    const now = Date.parse('2026-09-28T12:00:00Z');
    expect(formats.age('2026-09-28T11:55:00Z', now)).toBe('vor 5 Minuten');
    expect(formats.age('2026-09-28T09:00:00Z', now)).toBe('vor 3 Stunden');
    expect(formats.age('2026-09-25T12:00:00Z', now)).toBe('vor 3 Tagen');
    expect(formats.age(null, now)).toBeNull();
  });
});
