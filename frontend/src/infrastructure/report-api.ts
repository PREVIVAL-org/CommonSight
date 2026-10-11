/**
 * Sends error reports to `api/report.php` (Architecture 6.6): as a beacon, which also leaves a closing page, else as
 * a fetch. A report that does not arrive is lost; the visitor never notices either.
 */
import type { BrowserDiagnostics } from './browser-diagnostics';
import type { FetchFn } from './status-api';

export type ReportKind = 'map-start' | 'map-error' | 'context-lost' | 'script-error';

export interface ClientReport {
  kind: ReportKind;
  message: string;
  /** Map source of a map error, e.g. the basemap */
  source: string | null;
  stack: string | null;
  release: string;
  env: BrowserDiagnostics;
}

export interface ReportApi {
  send(report: ClientReport): void;
}

export class HttpReportApi implements ReportApi {
  constructor(
    private readonly baseUrl: string,
    private readonly fetchFn: FetchFn,
    private readonly beacon: ((url: string, data: Blob) => boolean) | null,
  ) {}

  send(report: ClientReport): void {
    const url = `${this.baseUrl}api/report.php`;
    const body = JSON.stringify(report);
    if (this.beacon?.(url, new Blob([body], { type: 'application/json' })) === true) return;
    this.fetchFn(url, {
      method: 'POST',
      body,
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      keepalive: true,
    }).catch(() => undefined);
  }
}
