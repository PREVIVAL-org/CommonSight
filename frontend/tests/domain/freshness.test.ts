/**
 * Freshness check against the shared test cases from contract/testcases/freshness.json (B-03, Architecture 3.4).
 */
import cases from '@contract/testcases/freshness.json';
import { describe, expect, it } from 'vitest';
import type { Level } from '../../src/contract/types';
import { checkFreshness, effectiveLevel } from '../../src/domain/freshness';

interface RecheckCase {
  name: string;
  assessment: { level: Level; validUntil?: string };
  now: string;
  expected: 'current' | 'expired' | 'missing';
}

describe('checkFreshness', () => {
  it.each((cases.recheck as RecheckCase[]).map((entry) => [entry.name, entry] as const))(
    '%s',
    (_name, entry) => {
      expect(checkFreshness(entry.assessment, Date.parse(entry.now))).toBe(entry.expected);
    },
  );

  it('treats an unparsable validUntil as missing', () => {
    expect(checkFreshness({ level: 'high', validUntil: 'gestern' }, 0)).toBe('missing');
  });
});

describe('effectiveLevel', () => {
  it('keeps the level only while the assessment is current', () => {
    const now = Date.parse('2026-09-28T12:00:00Z');
    expect(effectiveLevel({ level: 'high', validUntil: '2026-09-28T13:00:00Z' }, now)).toBe('high');
    expect(effectiveLevel({ level: 'high', validUntil: '2026-09-28T11:00:00Z' }, now)).toBe('unknown');
    expect(effectiveLevel({ level: 'elevated' }, now)).toBe('unknown');
  });
});
