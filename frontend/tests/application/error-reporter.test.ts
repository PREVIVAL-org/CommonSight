/**
 * Error reports to the server (Architecture 6.6): once per error, a few per page, diagnostics read once; only errors
 * of the own bundle; sent as a beacon, else as a fetch.
 */
import { describe, expect, it, vi } from 'vitest';
import { createErrorReporter, REPORTS_PER_PAGE } from '../../src/application/error-reporter';
import type { BrowserDiagnostics } from '../../src/infrastructure/browser-diagnostics';
import { readDiagnostics } from '../../src/infrastructure/browser-diagnostics';
import type { ClientReport } from '../../src/infrastructure/report-api';
import { HttpReportApi } from '../../src/infrastructure/report-api';
import { onScriptError } from '../../src/infrastructure/script-errors';

const ENV: BrowserDiagnostics = {
  userAgent: 'Mozilla/5.0',
  language: 'de-AT',
  webgl: 'none',
  renderer: null,
  softwareRendering: null,
  workers: true,
  canvasReadable: false,
  pixelRatio: 1,
  viewport: '1280x720',
};

function reporter() {
  const sent: ClientReport[] = [];
  const diagnostics = vi.fn(() => ENV);
  const errors = createErrorReporter({ api: { send: (r) => sent.push(r) }, diagnostics, release: '0.1.35' });
  return { errors, sent, diagnostics };
}

describe('createErrorReporter', () => {
  it('sends an error once with release and diagnostics', () => {
    const { errors, sent } = reporter();
    errors.report({ kind: 'map-error', message: 'AJAXError: Not Found (404)', source: 'basemap' });
    errors.report({ kind: 'map-error', message: 'AJAXError: Not Found (404)', source: 'basemap' });
    expect(sent).toEqual([
      {
        kind: 'map-error',
        message: 'AJAXError: Not Found (404)',
        source: 'basemap',
        stack: null,
        release: '0.1.35',
        env: ENV,
      },
    ]);
  });

  it('stops after a few reports per page and reads the diagnostics only once', () => {
    const { errors, sent, diagnostics } = reporter();
    for (let i = 0; i < 20; i++) errors.report({ kind: 'script-error', message: `TypeError ${i}` });
    expect(sent).toHaveLength(REPORTS_PER_PAGE);
    expect(diagnostics).toHaveBeenCalledTimes(1);
  });

  it('reads nothing about the browser while nothing goes wrong', () => {
    const { diagnostics } = reporter();
    expect(diagnostics).not.toHaveBeenCalled();
  });
});

describe('HttpReportApi', () => {
  const report: ClientReport = {
    kind: 'context-lost',
    message: 'webglcontextlost',
    source: null,
    stack: null,
    release: '1',
    env: ENV,
  };

  it('sends a beacon to api/report.php', async () => {
    const beacon = vi.fn((_url: string, _data: Blob) => true);
    const fetchFn = vi.fn();
    new HttpReportApi('/commonsight/', fetchFn, beacon).send(report);
    expect(beacon.mock.calls[0]?.[0]).toBe('/commonsight/api/report.php');
    expect(JSON.parse(await (beacon.mock.calls[0]?.[1] as Blob).text())).toEqual(report);
    expect(fetchFn).not.toHaveBeenCalled();
  });

  it('falls back to a fetch that keeps the cookies of the own site', () => {
    const fetchFn = vi.fn().mockRejectedValue(new Error('offline'));
    new HttpReportApi('/', fetchFn, () => false).send(report);
    new HttpReportApi('/', fetchFn, null).send(report);
    expect(fetchFn).toHaveBeenCalledTimes(2);
    expect(fetchFn.mock.calls[0]?.[1]).toMatchObject({
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
    });
  });
});

describe('onScriptError', () => {
  const BUNDLE = 'https://cs.example.org/app/0.1.35/';

  function fire(target: EventTarget, type: string, fields: Record<string, unknown>): void {
    target.dispatchEvent(Object.assign(new Event(type), fields));
  }

  it('reports errors of the own bundle and ignores those of the host page', () => {
    const win = new EventTarget() as unknown as Window;
    const listener = vi.fn();
    const stop = onScriptError(win, BUNDLE, listener);
    const ours = new TypeError('x is undefined');
    ours.stack = `TypeError: x is undefined\n    at ${BUNDLE}commonsight.js:1:2`;
    fire(win, 'error', { message: 'Uncaught TypeError', filename: `${BUNDLE}commonsight.js`, error: ours });
    fire(win, 'error', {
      message: 'Uncaught ReferenceError',
      filename: 'https://cs.example.org/forum.js',
      error: null,
    });
    fire(win, 'unhandledrejection', { reason: ours });
    fire(win, 'unhandledrejection', { reason: 'host page' });
    stop();
    fire(win, 'unhandledrejection', { reason: ours });
    expect(listener.mock.calls).toEqual([
      [{ message: 'TypeError: x is undefined', stack: ours.stack }],
      [{ message: 'TypeError: x is undefined', stack: ours.stack }],
    ]);
  });
});

describe('readDiagnostics', () => {
  /** A window whose canvases answer as given: WebGL contexts by kind and caveat, and the pixel a 2D canvas reads back. */
  function fakeWindow(gl: (kind: string, caveatFails: boolean) => object | null, pixel: number[]): Window {
    const canvas = {
      getContext: (kind: string, options?: { failIfMajorPerformanceCaveat?: boolean }) =>
        kind === '2d'
          ? { fillRect: () => undefined, getImageData: () => ({ data: pixel }) }
          : gl(kind, options?.failIfMajorPerformanceCaveat ?? false),
    };
    return {
      document: { createElement: () => canvas },
      navigator: { userAgent: 'Mozilla/5.0', language: 'de' },
      Worker: function Worker() {},
      devicePixelRatio: 2,
      innerWidth: 390,
      innerHeight: 844,
    } as unknown as Window;
  }

  it('names the graphics card and software rendering, and releases the contexts', () => {
    const loseContext = vi.fn();
    const webgl = {
      RENDERER: 1,
      getParameter: (name: number) => (name === 37446 ? 'llvmpipe (LLVM 17.0.6, 256 bits)' : 'WebKit WebGL'),
      getExtension: (name: string) =>
        name === 'WEBGL_debug_renderer_info' ? { UNMASKED_RENDERER_WEBGL: 37446 } : { loseContext },
    };
    const env = readDiagnostics(
      fakeWindow((kind, caveatFails) => (kind === 'webgl' && !caveatFails ? webgl : null), [10, 20, 30, 255]),
    );
    expect(env).toEqual({
      userAgent: 'Mozilla/5.0',
      language: 'de',
      webgl: 'webgl',
      renderer: 'llvmpipe (LLVM 17.0.6, 256 bits)',
      softwareRendering: true,
      workers: true,
      canvasReadable: true,
      pixelRatio: 2,
      viewport: '390x844',
    });
    expect(loseContext).toHaveBeenCalledTimes(1);
  });

  it('reports no WebGL and a canvas that reads back noise', () => {
    const env = readDiagnostics(fakeWindow(() => null, [11, 20, 29, 255]));
    expect(env).toMatchObject({
      webgl: 'none',
      renderer: null,
      softwareRendering: null,
      canvasReadable: false,
    });
  });
});
