/**
 * Data orchestration with test doubles: only needed, changed snapshots; errors; abort (U-50, U-52, U-54, U-55).
 */
import { describe, expect, it } from 'vitest';
import { DataSync } from '../../src/application/data-sync';
import type { Snapshot } from '../../src/contract/types';
import { snapshot, statusAT, statusFor } from '../support/fixtures';
import { FakeSnapshotApi, FakeStatusApi, FixedClock, settle } from '../support/doubles';
import { NOW } from '../support/deps';
import { createTestStore } from '../support/store';

function snapshotsByUrl(): Record<string, Snapshot> {
  return {
    'data/v1/AT/warnings.3f9a1c2e.json': snapshot('warnings-AT'),
    'data/v1/AT/weather.aa000001.json': snapshot('weather-AT'),
    'data/v1/AT/air.aa000002.json': snapshot('air-AT'),
    'data/v1/AT/radiation.aa000003.json': snapshot('radiation-AT'),
    'data/v1/global/space.aa000004.json': snapshot('space-global'),
    'data/v1/global/news.aa000007.json': snapshot('news-global'),
    'data/v1/AT/traffic.aa000006.json': snapshot('traffic-AT'),
    'data/v1/AT/nature.aa000005.json': snapshot('nature-AT'),
  };
}

/** Only the selected country; the switch "Grenzgebiet" is tested separately below. */
function setup() {
  const bundle = createTestStore('AT');
  bundle.actions.setBorderZone(false);
  const status = new FakeStatusApi({ AT: statusAT() });
  const snapshots = new FakeSnapshotApi(snapshotsByUrl());
  const sync = new DataSync({ status, snapshots }, bundle.store, bundle.actions, new FixedClock(NOW));
  return { ...bundle, status, snapshots, sync };
}

describe('DataSync', () => {
  it('loads status and only the snapshots needed by the overview', async () => {
    const { store, snapshots, sync } = setup();
    await sync.syncStatus();
    expect(snapshots.calls.sort()).toEqual([
      'data/v1/AT/air.aa000002.json',
      'data/v1/AT/radiation.aa000003.json',
      'data/v1/AT/warnings.3f9a1c2e.json',
      'data/v1/AT/weather.aa000001.json',
      'data/v1/global/news.aa000007.json',
      'data/v1/global/space.aa000004.json',
    ]);
    expect(Object.keys(store.getState().data.snapshots).sort()).toHaveLength(6);
    expect(store.getState().data.syncing).toBe(false);
  });

  it('does not reload unchanged versions and loads newly needed layers', async () => {
    const { actions, snapshots, sync } = setup();
    await sync.syncStatus();
    const before = snapshots.calls.length;
    await sync.syncStatus();
    expect(snapshots.calls.length).toBe(before);
    actions.toggleLayer('traffic');
    await sync.syncSnapshots();
    expect(snapshots.calls.at(-1)).toBe('data/v1/AT/traffic.aa000006.json');
  });

  it('marks a failed snapshot and keeps the previous data (U-54)', async () => {
    const { store, snapshots, sync } = setup();
    snapshots.failing.add('data/v1/AT/weather.aa000001.json');
    await sync.syncStatus();
    expect(store.getState().data.failed).toEqual(['AT/weather']);
    expect(store.getState().data.snapshots['AT/warnings']).toBeDefined();
  });

  it('marks the status as failed when the endpoint is unreachable', async () => {
    const { store, status, sync } = setup();
    status.failWith = new Error('offline');
    await sync.syncStatus();
    expect(store.getState().data.statusFailed).toEqual(['AT']);
  });

  it('ignores aborts and aborts running requests (U-55)', async () => {
    const { store, status, sync } = setup();
    const pending = sync.syncStatus();
    sync.abortAll();
    await pending;
    expect(status.signals[0]?.aborted).toBe(true);
    expect(store.getState().data.statuses).toEqual({});
    expect(store.getState().data.statusFailed).toEqual([]);
  });

  it('asks every country for "Alle" and loads global layers only once (ADR 0037)', async () => {
    const bundle = createTestStore('ALL');
    const status = new FakeStatusApi({ DE: statusFor('DE'), AT: statusAT(), CH: statusFor('CH') });
    const byUrl: Record<string, Snapshot> = { ...snapshotsByUrl() };
    for (const country of ['DE', 'CH'] as const) {
      for (const [url, data] of Object.entries(snapshotsByUrl())) {
        if (url.includes('/AT/')) byUrl[url.replace('/AT/', `/${country}/`)] = { ...data, scope: country };
      }
    }
    const snapshots = new FakeSnapshotApi(byUrl);
    await new DataSync({ status, snapshots }, bundle.store, bundle.actions, new FixedClock(NOW)).syncStatus();
    expect(status.calls.sort()).toEqual(['AT', 'CH', 'DE']);
    expect(snapshots.calls.filter((url) => url.includes('global/news'))).toHaveLength(1);
    expect(snapshots.calls.filter((url) => url.includes('/warnings.'))).toHaveLength(3);
    expect(Object.keys(bundle.store.getState().data.statuses).sort()).toEqual(['AT', 'CH', 'DE']);
  });

  it('asks all three countries with "Grenzgebiet" and loads the needed layers of the neighbours (ADR 0038)', async () => {
    const bundle = createTestStore('AT');
    const status = new FakeStatusApi({ DE: statusFor('DE'), AT: statusAT(), CH: statusFor('CH') });
    const byUrl: Record<string, Snapshot> = { ...snapshotsByUrl() };
    for (const country of ['DE', 'CH'] as const) {
      for (const [url, data] of Object.entries(snapshotsByUrl())) {
        if (url.includes('/AT/')) byUrl[url.replace('/AT/', `/${country}/`)] = { ...data, scope: country };
      }
    }
    const snapshots = new FakeSnapshotApi(byUrl);
    await new DataSync({ status, snapshots }, bundle.store, bundle.actions, new FixedClock(NOW)).syncStatus();
    expect(status.calls.sort()).toEqual(['AT', 'CH', 'DE']);
    expect(snapshots.calls.filter((url) => url.includes('/weather.')).sort()).toEqual([
      'data/v1/AT/weather.aa000001.json',
      'data/v1/CH/weather.aa000001.json',
      'data/v1/DE/weather.aa000001.json',
    ]);
    // The selection stays Austria: status, availability and live display only look at it.
    expect(bundle.store.getState().selection.country).toBe('AT');
  });

  it('keeps the guard of a new run when an aborted one ends (U-55)', async () => {
    const { status, sync } = setup();
    const aborted = sync.syncStatus();
    sync.abortAll();
    const current = sync.syncStatus();
    await aborted;
    const third = sync.syncStatus();
    await Promise.all([current, third]);
    await settle();
    expect(status.calls, 'the aborted run and the new one, no third in parallel').toEqual(['AT', 'AT']);
  });

  it('fetches the neighbours right after a running fetch when "Grenzgebiet" is switched on meanwhile', async () => {
    const { store, actions } = createTestStore('AT');
    actions.setBorderZone(false);
    const status = new FakeStatusApi({ DE: statusFor('DE'), AT: statusAT(), CH: statusFor('CH') });
    const sync = new DataSync(
      { status, snapshots: new FakeSnapshotApi(snapshotsByUrl()) },
      store,
      actions,
      new FixedClock(NOW),
    );
    const running = sync.syncStatus();
    actions.setBorderZone(true);
    const wider = sync.syncStatus();
    await Promise.all([running, wider]);
    await settle();
    expect(status.calls.sort()).toEqual(['AT', 'AT', 'CH', 'DE']);
  });

  it('does not start a fetch queued before a country switch', async () => {
    const { store, actions } = createTestStore('AT');
    actions.setBorderZone(false);
    const status = new FakeStatusApi({ DE: statusFor('DE'), AT: statusAT(), CH: statusFor('CH') });
    const sync = new DataSync(
      { status, snapshots: new FakeSnapshotApi(snapshotsByUrl()) },
      store,
      actions,
      new FixedClock(NOW),
    );
    const running = sync.syncStatus();
    actions.setBorderZone(true);
    const queued = sync.syncStatus();
    sync.abortAll();
    const current = sync.syncStatus();
    await Promise.all([running, queued, current]);
    await settle();
    expect(status.calls.sort(), 'the aborted one and the new one, not the queued one').toEqual([
      'AT',
      'AT',
      'CH',
      'DE',
    ]);
  });

  it('runs at most one status request at a time', async () => {
    const { status, sync } = setup();
    await Promise.all([sync.syncStatus(), sync.syncStatus()]);
    await settle();
    expect(status.calls).toEqual(['AT']);
  });
});
