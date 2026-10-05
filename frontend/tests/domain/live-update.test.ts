/**
 * Visible automatic update: state, countdown and hint below the map, count of new warnings (U-50 to U-54).
 */
import { describe, expect, it } from 'vitest';
import type { Snapshot } from '../../src/contract/types';
import { dataReceivedAtMs, liveStateOf, selectedFailed } from '../../src/domain/live-status';
import { newEntryCount } from '../../src/domain/new-entries';
import { liveDisplayOf, secondsUntil, toLiveView } from '../../src/domain/views/live-view';
import { snapshot } from '../support/fixtures';
import { NOW, viewDeps } from '../support/deps';

const MINUTE = 60_000;

describe('live state', () => {
  it('is offline first, then disturbed, then loading until every selected country has a status', () => {
    expect(liveStateOf({ online: false, receivedAtMs: [NOW], failed: true })).toBe('offline');
    expect(liveStateOf({ online: true, receivedAtMs: [NOW], failed: true })).toBe('failed');
    expect(liveStateOf({ online: true, receivedAtMs: [NOW, undefined], failed: false })).toBe('pending');
    expect(liveStateOf({ online: true, receivedAtMs: [NOW, NOW], failed: false })).toBe('live');
  });

  it('takes the oldest status of the selection and only the selected countries as failed', () => {
    expect(dataReceivedAtMs([NOW, NOW - MINUTE, undefined])).toBe(NOW - MINUTE);
    expect(dataReceivedAtMs([undefined])).toBeNull();
    expect(selectedFailed(['AT'], ['DE'])).toBe(false);
    expect(selectedFailed(['DE', 'AT', 'CH'], ['CH'])).toBe(true);
  });
});

describe('live view', () => {
  it('counts down to the next update in whole seconds, never below zero', () => {
    expect(secondsUntil(NOW + MINUTE, NOW + 18_500)).toBe(42);
    expect(secondsUntil(NOW, NOW + MINUTE)).toBe(0);
    expect(secondsUntil(null, NOW)).toBeNull();
    expect(toLiveView('live', 42, NOW, viewDeps()).text).toBe('Aktualisierung in 42 s');
    expect(toLiveView('live', null, NOW, viewDeps()).text).toBe('Automatische Aktualisierung');
  });

  it('shows the running fetch, the retry after a failure and the pause when offline', () => {
    expect(liveDisplayOf('live', true)).toBe('syncing');
    expect(liveDisplayOf('offline', true)).toBe('offline');
    expect(toLiveView('syncing', 60, NOW, viewDeps()).text).toBe('Wird aktualisiert ...');
    expect(toLiveView('failed', 42, NOW, viewDeps()).text).toBe(
      'Aktualisierung fehlgeschlagen · neuer Versuch in 42 s',
    );
    expect(toLiveView('offline', 42, NOW, viewDeps()).text).toBe('Offline · Aktualisierung pausiert');
    expect(toLiveView('pending', null, null, viewDeps()).text).toBe('Wird geladen ...');
  });

  it('has a short form with the seconds for narrow screens', () => {
    expect(toLiveView('live', 42, NOW, viewDeps()).short).toBe('42 s');
    expect(toLiveView('failed', 42, NOW, viewDeps()).short).toBe('42 s');
    expect(toLiveView('offline', 42, NOW, viewDeps()).short).toBe('Offline');
    expect(toLiveView('syncing', 60, NOW, viewDeps()).short).toBe('');
  });

  it('explains the display with the time of the last fetch', () => {
    expect(toLiveView('live', 42, NOW, viewDeps()).hint).toMatch(
      /automatisch jede Minute abgeglichen.*Neu laden ist nicht nötig\. Letzter Abgleich: 28\.09\., 14:00$/,
    );
    expect(toLiveView('pending', null, null, viewDeps()).hint).toMatch(/nicht nötig\.$/);
  });
});

describe('new entries', () => {
  const before = snapshot('warnings-AT');
  const extra = { ...before.items[0], id: 'neu-1' } as Snapshot['items'][number];
  const after: Snapshot = { ...before, items: [...before.items, extra] };

  it('counts warnings that a newer version of a loaded snapshot adds', () => {
    expect(
      newEntryCount(
        { 'AT/warnings': { version: 'v1', snapshot: before } },
        { 'AT/warnings': { version: 'v2', snapshot: after } },
        ['AT/warnings'],
      ),
    ).toBe(1);
  });

  it('does not count the first load, an unchanged version or snapshots outside the keys', () => {
    expect(newEntryCount({}, { 'AT/warnings': { version: 'v1', snapshot: after } }, ['AT/warnings'])).toBe(0);
    expect(
      newEntryCount(
        { 'AT/warnings': { version: 'v1', snapshot: before } },
        { 'AT/warnings': { version: 'v1', snapshot: after } },
        ['AT/warnings'],
      ),
    ).toBe(0);
    expect(
      newEntryCount(
        { 'AT/traffic': { version: 'v1', snapshot: before } },
        { 'AT/traffic': { version: 'v2', snapshot: after } },
        ['AT/warnings'],
      ),
    ).toBe(0);
  });
});
