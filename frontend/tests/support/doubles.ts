/**
 * Test doubles for I/O: controllable interval timer, fixed clock, status and snapshot sources, storage.
 */
import type { Country, Snapshot, StatusResponse } from '../../src/contract/types';
import type { SnapshotApi } from '../../src/infrastructure/snapshot-api';
import type { SettingsStorage } from '../../src/infrastructure/settings-storage';
import type { StatusApi } from '../../src/infrastructure/status-api';
import type { Clock, Timer } from '../../src/infrastructure/timer';

export class ManualTimer implements Timer {
  private readonly intervals = new Set<{ ms: number; callback: () => void }>();
  private readonly timeouts = new Set<{ ms: number; callback: () => void }>();

  every(ms: number, callback: () => void): () => void {
    const entry = { ms, callback };
    this.intervals.add(entry);
    return () => this.intervals.delete(entry);
  }

  after(ms: number, callback: () => void): () => void {
    const entry = { ms, callback };
    this.timeouts.add(entry);
    return () => this.timeouts.delete(entry);
  }

  /** Fires all interval timers with this interval once. */
  fire(ms: number): void {
    [...this.intervals].filter((entry) => entry.ms === ms).forEach((entry) => entry.callback());
  }

  /** Fires all one-shot timers. */
  flushTimeouts(): void {
    const due = [...this.timeouts];
    this.timeouts.clear();
    due.forEach((entry) => entry.callback());
  }

  get activeIntervals(): number {
    return this.intervals.size;
  }
}

export class FixedClock implements Clock {
  constructor(public current: number) {}
  now(): number {
    return this.current;
  }
}

export class FakeStatusApi implements StatusApi {
  readonly calls: Country[] = [];
  readonly signals: AbortSignal[] = [];
  failWith: Error | null = null;

  constructor(private readonly responses: Partial<Record<Country, StatusResponse>>) {}

  async fetchStatus(country: Country, signal: AbortSignal): Promise<StatusResponse> {
    this.calls.push(country);
    this.signals.push(signal);
    if (this.failWith !== null) throw this.failWith;
    const response = this.responses[country];
    if (response === undefined) throw new Error(`no status for ${country}`);
    return response;
  }
}

export class FakeSnapshotApi implements SnapshotApi {
  readonly calls: string[] = [];
  readonly failing = new Set<string>();

  constructor(private readonly byUrl: Record<string, Snapshot>) {}

  async fetchSnapshot(path: string): Promise<Snapshot> {
    this.calls.push(path);
    const snapshot = this.byUrl[path];
    if (this.failing.has(path) || snapshot === undefined) throw new Error(`404 ${path}`);
    return snapshot;
  }
}

export class MemorySettingsStorage implements SettingsStorage {
  settings: unknown = null;
  theme: unknown = null;
  writes = 0;

  readSettings(): unknown {
    return this.settings;
  }
  writeSettings(value: unknown): void {
    this.settings = value;
    this.writes += 1;
  }
  readTheme(): unknown {
    return this.theme;
  }
  writeTheme(value: string): void {
    this.theme = value;
  }
}

/** Waits until all pending promise continuations have run. */
export async function settle(): Promise<void> {
  for (let i = 0; i < 10; i += 1) await Promise.resolve();
}
