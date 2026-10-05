/**
 * I/O with fake fetch or storage: ETag/304, contract check, fault-tolerant storage (U-52, U-60).
 */
import { describe, expect, it, vi } from 'vitest';
import { HttpSnapshotApi } from '../../src/infrastructure/snapshot-api';
import { createSettingsStorage } from '../../src/infrastructure/settings-storage';
import { HttpStatusApi } from '../../src/infrastructure/status-api';
import { ContractShapeError, HttpStatusError } from '../../src/infrastructure/response-errors';
import { snapshot, statusAT } from '../support/fixtures';

function response(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(status === 304 ? null : JSON.stringify(body), { status, headers });
}

describe('HttpStatusApi', () => {
  it('sends If-None-Match and returns the cached body on 304', async () => {
    const body = statusAT();
    const fetchFn = vi
      .fn()
      .mockResolvedValueOnce(response(200, body, { ETag: '"abc"' }))
      .mockResolvedValueOnce(response(304, null));
    const api = new HttpStatusApi('/commonsight/', fetchFn);
    const signal = new AbortController().signal;
    const first = await api.fetchStatus('AT', signal);
    const second = await api.fetchStatus('AT', signal);
    expect(second).toBe(first);
    expect(fetchFn.mock.calls[0]?.[0]).toBe('/commonsight/api/status.php?scope=AT');
    expect(fetchFn.mock.calls[1]?.[1]).toMatchObject({ headers: { 'If-None-Match': '"abc"' } });
  });

  it('rejects HTTP errors and responses outside the contract', async () => {
    const signal = new AbortController().signal;
    await expect(
      new HttpStatusApi('/', vi.fn().mockResolvedValue(response(500, {}))).fetchStatus('DE', signal),
    ).rejects.toBeInstanceOf(HttpStatusError);
    await expect(
      new HttpStatusApi('/', vi.fn().mockResolvedValue(response(200, { schema: 2 }))).fetchStatus(
        'DE',
        signal,
      ),
    ).rejects.toBeInstanceOf(ContractShapeError);
  });
});

describe('HttpSnapshotApi', () => {
  it('loads a snapshot relative to the base url', async () => {
    const fetchFn = vi.fn().mockResolvedValue(response(200, snapshot('nature-AT')));
    const result = await new HttpSnapshotApi('/commonsight/', fetchFn).fetchSnapshot(
      'data/v1/AT/nature.aa000005.json',
      new AbortController().signal,
    );
    expect(result.layer).toBe('nature');
    expect(fetchFn.mock.calls[0]?.[0]).toBe('/commonsight/data/v1/AT/nature.aa000005.json');
  });
});

describe('settings storage', () => {
  it('reads and writes JSON under the configured key (U-61)', () => {
    const map = new Map<string, string>();
    const storage = {
      getItem: (key: string) => map.get(key) ?? null,
      setItem: (key: string, value: string) => void map.set(key, value),
    } as Storage;
    const settings = createSettingsStorage(storage, 'cs-user-1');
    settings.writeSettings({ country: 'DE' });
    settings.writeTheme('dark');
    expect(map.get('cs-user-1')).toBe('{"country":"DE"}');
    expect(settings.readSettings()).toEqual({ country: 'DE' });
    expect(settings.readTheme()).toBe('dark');
  });

  it('tolerates missing, blocked and corrupt storage (U-60)', () => {
    const blocked = {
      getItem: () => {
        throw new Error('SecurityError');
      },
      setItem: () => {
        throw new Error('QuotaExceeded');
      },
    } as unknown as Storage;
    expect(createSettingsStorage(null, 'k').readSettings()).toBeNull();
    expect(createSettingsStorage(blocked, 'k').readSettings()).toBeNull();
    expect(() => createSettingsStorage(blocked, 'k').writeSettings({})).not.toThrow();
    const corrupt = { getItem: () => '{kaputt', setItem: () => undefined } as unknown as Storage;
    expect(createSettingsStorage(corrupt, 'k').readSettings()).toBeNull();
  });
});
