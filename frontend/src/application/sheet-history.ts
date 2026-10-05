/**
 * Ties the detail sheet to the browser history: every sheet opened adds an entry, "back" returns step by step
 * to the previous sheet and finally closes it; closing via the cross removes the own entries again, so that a
 * later "back" leaves the page as usual.
 */
import type { SheetState } from '../domain/selection';
import type { BrowserHistory } from '../infrastructure/browser-history';
import type { AppActions, AppStore } from '../state/store';

export function startSheetHistory(store: AppStore, actions: AppActions, history: BrowserHistory): () => void {
  // Sheets opened in this order; the last one is shown.
  let stack: SheetState[] = [];
  // Set while a "back" is applied, so that the resulting store change does not add an entry again.
  let applying = false;

  const unsubscribe = store.subscribe((state, previous) => {
    const sheet = state.ui.sheet;
    if (applying || sheet === previous.ui.sheet) return;
    if (sheet !== null) {
      stack = [...stack, sheet];
      history.push(stack.length);
      return;
    }
    // Closed in the UI (cross, Escape, click outside, country switch): remove the own entries.
    const steps = stack.length;
    stack = [];
    history.back(steps);
  });

  const stopPop = history.onPop((depth) => {
    // Forward or an entry of the host page: not a step back within the sheets.
    if (depth >= stack.length) return;
    stack = stack.slice(0, depth);
    applying = true;
    const top = stack.at(-1);
    if (top === undefined) actions.closeSheet();
    else actions.openSheet(top);
    applying = false;
  });

  return () => {
    unsubscribe();
    stopPop();
  };
}
