/**
 * Entries in the browser history without changing the address, so that "back" (phone button, swipe, browser)
 * can close the detail sheet; the host page's own history state is kept.
 */

export type Unsubscribe = () => void;

export interface BrowserHistory {
  /** Adds an entry at the current address; `depth` identifies it later. */
  push(depth: number): void;
  /** Goes `steps` entries back, as if "back" had been pressed that often. */
  back(steps: number): void;
  /** Reports the depth of the entry the browser moved to (0 = not one of ours). */
  onPop(listener: (depth: number) => void): Unsubscribe;
}

/** The elements of this page in the order they first used the history: each keeps its entries under its own key. */
const elements = new WeakMap<object, number>();
let known = 0;

function depthOf(state: unknown, key: string): number {
  if (typeof state !== 'object' || state === null || !(key in state)) return 0;
  const value = (state as Record<string, unknown>)[key];
  return typeof value === 'number' ? value : 0;
}

/**
 * Several elements on one page each have their own key (the first one the plain one, as before); an entry keeps the depths of the others (it extends the
 * current state), so "back" closes the sheet of exactly the element that opened it last.
 */
export function createBrowserHistory(win: Window, element: object): BrowserHistory {
  // The same key again when the element is moved in the page and assembled anew.
  const index = elements.get(element) ?? known++;
  elements.set(element, index);
  const KEY = `commonsightSheet${index === 0 ? '' : `-${index}`}`;
  return {
    push: (depth) => {
      const current =
        typeof win.history.state === 'object' && win.history.state !== null ? win.history.state : {};
      win.history.pushState({ ...current, [KEY]: depth }, '');
    },
    back: (steps) => {
      if (steps > 0) win.history.go(-steps);
    },
    onPop: (listener) => {
      const handler = (event: PopStateEvent): void => listener(depthOf(event.state, KEY));
      win.addEventListener('popstate', handler);
      return () => win.removeEventListener('popstate', handler);
    },
  };
}
