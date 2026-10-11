/**
 * Reports uncaught errors and rejected promises of the own bundle (Architecture 6.6). The element shares the window
 * with the host page: errors whose file or stack is not below the bundle are not ours and are ignored.
 */

export type Unsubscribe = () => void;

export interface ScriptError {
  message: string;
  stack: string | null;
}

function describe(reason: unknown): ScriptError {
  if (reason instanceof Error)
    return { message: `${reason.name}: ${reason.message}`, stack: reason.stack ?? null };
  return { message: String(reason), stack: null };
}

export function onScriptError(
  win: Window,
  bundleUrl: string,
  listener: (error: ScriptError) => void,
): Unsubscribe {
  const ours = (file: string | null | undefined, stack: string | null): boolean =>
    (file ?? '').startsWith(bundleUrl) || (stack ?? '').includes(bundleUrl);
  const error = (event: ErrorEvent): void => {
    const described =
      event.error === undefined || event.error === null
        ? { message: event.message, stack: null }
        : describe(event.error);
    if (ours(event.filename, described.stack)) listener(described);
  };
  const rejection = (event: PromiseRejectionEvent): void => {
    const described = describe(event.reason);
    if (ours(null, described.stack)) listener(described);
  };
  win.addEventListener('error', error);
  win.addEventListener('unhandledrejection', rejection);
  return () => {
    win.removeEventListener('error', error);
    win.removeEventListener('unhandledrejection', rejection);
  };
}
