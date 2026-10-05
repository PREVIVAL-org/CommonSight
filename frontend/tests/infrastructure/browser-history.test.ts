/**
 * Browser history of the detail sheets: two elements on one page keep their entries apart (U-81).
 */
import { describe, expect, it, vi } from 'vitest';
import { createBrowserHistory } from '../../src/infrastructure/browser-history';

/** A window whose history is a list of states; go(-n) moves back and reports popstate. */
function fakeWindow() {
  const entries: unknown[] = [null];
  let index = 0;
  const listeners: ((event: { state: unknown }) => void)[] = [];
  const win = {
    history: {
      get state() {
        return entries[index];
      },
      pushState(state: unknown) {
        entries.splice(index + 1, entries.length, state);
        index = entries.length - 1;
      },
      go(delta: number) {
        index = Math.max(0, index + delta);
        for (const listener of listeners) listener({ state: entries[index] });
      },
    },
    addEventListener: (_type: string, listener: (event: { state: unknown }) => void) =>
      listeners.push(listener),
    removeEventListener: () => undefined,
  };
  return win as unknown as Window;
}

describe('browser history of the sheets', () => {
  it('closes only the sheet of the element that opened it last', () => {
    const win = fakeWindow();
    const element = {};
    const first = createBrowserHistory(win, element);
    const second = createBrowserHistory(win, {});
    const depths: { first: number[]; second: number[] } = { first: [], second: [] };
    first.onPop((depth) => depths.first.push(depth));
    second.onPop((depth) => depths.second.push(depth));

    first.push(1);
    second.push(1);
    first.back(1);

    expect(depths).toEqual({ first: [1], second: [0] });
  });

  it('keeps the key of an element that is moved in the page and assembled anew', () => {
    const win = fakeWindow();
    const element = {};
    createBrowserHistory(win, element).push(1);
    const depths: number[] = [];
    createBrowserHistory(win, element).onPop((depth) => depths.push(depth));
    win.history.pushState({}, '');
    win.history.go(-1);

    expect(depths, 'the entry it pushed before is still its own').toEqual([1]);
  });

  it('gives the first element of a page the plain key, so entries from before still match', async () => {
    vi.resetModules();
    const fresh = await import('../../src/infrastructure/browser-history');
    const win = fakeWindow();
    fresh.createBrowserHistory(win, {}).push(1);
    expect(win.history.state).toEqual({ commonsightSheet: 1 });
    fresh.createBrowserHistory(win, {}).push(1);
    expect(win.history.state).toEqual({ commonsightSheet: 1, 'commonsightSheet-1': 1 });
  });
});
