/**
 * "Back" closes the detail sheet step by step; closing via the cross removes the own history entries (U-40 to U-44).
 */
import { describe, expect, it } from 'vitest';
import { startSheetHistory } from '../../src/application/sheet-history';
import type { BrowserHistory } from '../../src/infrastructure/browser-history';
import { createTestStore } from '../support/store';

/** Browser history double: a list of depths and a cursor, like the real back and forward. */
class FakeHistory implements BrowserHistory {
  entries = [0];
  index = 0;
  private listener: ((depth: number) => void) | null = null;

  push(depth: number): void {
    this.entries = [...this.entries.slice(0, this.index + 1), depth];
    this.index = this.entries.length - 1;
  }

  back(steps: number): void {
    if (steps <= 0) return;
    this.index = Math.max(0, this.index - steps);
    this.listener?.(this.entries[this.index] ?? 0);
  }

  onPop(listener: (depth: number) => void): () => void {
    this.listener = listener;
    return () => {
      this.listener = null;
    };
  }
}

function setup() {
  const bundle = createTestStore('AT');
  const history = new FakeHistory();
  startSheetHistory(bundle.store, bundle.actions, history);
  return { ...bundle, history };
}

describe('sheet history', () => {
  it('goes back from an item to the layer and then closes the sheet', () => {
    const { store, actions, history } = setup();
    actions.openSheet({ type: 'layer', layer: 'warnings' });
    actions.openSheet({ type: 'item', layer: 'warnings', itemId: 'a' });
    expect(history.entries).toEqual([0, 1, 2]);
    history.back(1);
    expect(store.getState().ui.sheet).toEqual({ type: 'layer', layer: 'warnings' });
    history.back(1);
    expect(store.getState().ui.sheet).toBeNull();
    expect(history.index).toBe(0);
  });

  it('removes the own entries when the sheet is closed via the cross', () => {
    const { store, actions, history } = setup();
    actions.openSheet({ type: 'sources' });
    actions.openSheet({ type: 'layer', layer: 'water' });
    actions.closeSheet();
    expect(history.index).toBe(0);
    expect(store.getState().ui.sheet).toBeNull();
    actions.openSheet({ type: 'sources' });
    expect(history.entries).toEqual([0, 1]);
  });
});
