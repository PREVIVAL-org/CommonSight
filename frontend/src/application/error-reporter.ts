/**
 * Reports errors of the map and the bundle to the server (Architecture 6.6): each error once per page, at most a few
 * per page, with what the browser allows the map. The diagnostics are read at the first report only.
 */
import type { BrowserDiagnostics } from '../infrastructure/browser-diagnostics';
import type { ReportApi, ReportKind } from '../infrastructure/report-api';

/** A broken release or a flapping connection would otherwise send a report per tile. */
export const REPORTS_PER_PAGE = 5;

export interface ReportedError {
  kind: ReportKind;
  message: string;
  source?: string | null;
  stack?: string | null;
}

export interface ErrorReporter {
  report(error: ReportedError): void;
}

export interface ErrorReporterDeps {
  api: ReportApi;
  diagnostics: () => BrowserDiagnostics;
  release: string;
}

export function createErrorReporter({ api, diagnostics, release }: ErrorReporterDeps): ErrorReporter {
  const sent = new Set<string>();
  let env: BrowserDiagnostics | null = null;
  return {
    report: ({ kind, message, source = null, stack = null }) => {
      const key = `${kind}|${source ?? ''}|${message}`;
      if (sent.has(key) || sent.size >= REPORTS_PER_PAGE) return;
      sent.add(key);
      env ??= diagnostics();
      api.send({ kind, message, source, stack, release, env });
    },
  };
}
