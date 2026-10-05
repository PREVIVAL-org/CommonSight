/**
 * Warnings: the map notice about the warning situation (U-23) and the short notice about new warnings.
 */
import type { NoticeInput } from '@sdk/ui';
import { testFormat } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { ui } from './ui';

function notice(input: Omit<NoticeInput, 'format'>) {
  if (ui.notice === undefined) throw new Error('warnings without a map notice');
  return ui.notice.summary({ ...input, format: testFormat() });
}

describe('warnings map notice (U-23)', () => {
  it('phrases the regional warning count with incompleteness and unassigned hint', () => {
    expect(notice({ availability: 'partial', count: 2, unassigned: 1, regionName: 'Wien' })).toEqual({
      tone: 'warn',
      text: '2 Warnungen in Wien · unvollständig · weitere Meldungen ohne Ortszuordnung',
      hasEntries: true,
    });
  });

  it('phrases the country-wide, loading and error cases', () => {
    expect(notice({ availability: 'ok', count: 1, unassigned: 0, regionName: null }).text).toBe(
      '1 Warnung in den verbundenen Quellen',
    );
    expect(notice({ availability: 'ok', count: 0, unassigned: 0, regionName: null })).toEqual({
      tone: 'info',
      text: 'Keine Warnungen in den verbundenen Quellen',
      hasEntries: false,
    });
    expect(notice({ availability: 'error', count: 0, unassigned: 0, regionName: null }).text).toBe(
      'Warnquelle derzeit nicht erreichbar',
    );
    expect(notice({ availability: 'setup', count: 0, unassigned: 0, regionName: null }).tone).toBe('error');
    expect(notice({ availability: 'pending', count: 0, unassigned: 0, regionName: null }).text).toBe(
      'Warnungen werden abgerufen ...',
    );
  });

  it('counts new warnings in the plural form', () => {
    const format = testFormat();
    const key = ui.newEntriesToast ?? '';
    expect(format.text({ key, params: { count: 1 } })).toBe('1 neue amtliche Warnung');
    expect(format.text({ key, params: { count: 3 } })).toBe('3 neue amtliche Warnungen');
  });
});
