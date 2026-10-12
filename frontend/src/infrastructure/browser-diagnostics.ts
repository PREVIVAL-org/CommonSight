/**
 * Reads what the browser allows the map (Architecture 6.6): WebGL and the graphics card, workers, a readable canvas.
 * Only read when an error is reported, never at the start.
 */

export interface BrowserDiagnostics {
  userAgent: string;
  language: string;
  webgl: 'webgl2' | 'webgl' | 'none';
  /** Graphics card as WebGL names it; null without WebGL */
  renderer: string | null;
  /** WebGL only with a major performance caveat (software rendering, blocklisted driver); null without WebGL */
  softwareRendering: boolean | null;
  workers: boolean;
  /** A pixel drawn on a canvas reads back unchanged; false under fingerprinting protection; null without canvas */
  canvasReadable: boolean | null;
  pixelRatio: number;
  viewport: string;
}

type GlContext = WebGLRenderingContext | WebGL2RenderingContext;

function context(doc: Document, kind: 'webgl2' | 'webgl', caveatFails: boolean): GlContext | null {
  try {
    const canvas = doc.createElement('canvas');
    return canvas.getContext(kind, { failIfMajorPerformanceCaveat: caveatFails }) as GlContext | null;
  } catch {
    return null;
  }
}

/** Releases the context at once: browsers allow only a few at a time, and the map may still need one. */
function release(gl: GlContext): void {
  gl.getExtension('WEBGL_lose_context')?.loseContext();
}

/** MapLibre needs WebGL2; without it the map cannot start (switched off, blocklisted, no graphics card). */
export function hasWebgl2(doc: Document): boolean {
  const gl = context(doc, 'webgl2', false);
  if (gl !== null) release(gl);
  return gl !== null;
}

function rendererOf(gl: GlContext): string | null {
  try {
    const info = gl.getExtension('WEBGL_debug_renderer_info');
    const value: unknown = gl.getParameter(info === null ? gl.RENDERER : info.UNMASKED_RENDERER_WEBGL);
    return typeof value === 'string' && value !== '' ? value : null;
  } catch {
    return null;
  }
}

function probeWebgl(doc: Document): Pick<BrowserDiagnostics, 'webgl' | 'renderer' | 'softwareRendering'> {
  for (const kind of ['webgl2', 'webgl'] as const) {
    const gl = context(doc, kind, false);
    if (gl === null) continue;
    const renderer = rendererOf(gl);
    release(gl);
    const fast = context(doc, kind, true);
    if (fast !== null) release(fast);
    return { webgl: kind, renderer, softwareRendering: fast === null };
  }
  return { webgl: 'none', renderer: null, softwareRendering: null };
}

function canvasReadable(doc: Document): boolean | null {
  try {
    const canvas = doc.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    const ctx = canvas.getContext('2d');
    if (ctx === null) return null;
    ctx.fillStyle = 'rgb(10, 20, 30)';
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
    return r === 10 && g === 20 && b === 30;
  } catch {
    return false;
  }
}

export function readDiagnostics(win: Window): BrowserDiagnostics {
  const doc = win.document;
  return {
    userAgent: win.navigator.userAgent,
    language: win.navigator.language,
    ...probeWebgl(doc),
    workers: typeof (win as Window & { Worker?: unknown }).Worker === 'function',
    canvasReadable: canvasReadable(doc),
    pixelRatio: win.devicePixelRatio,
    viewport: `${win.innerWidth}x${win.innerHeight}`,
  };
}
