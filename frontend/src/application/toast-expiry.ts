/**
 * Hides each toast again after a few seconds (U-05).
 */
import type { Timer } from '../infrastructure/timer';
import type { UiActions } from '../state/ui-actions';
import type { AppStore } from '../state/store';

export const TOAST_DURATION_MS = 6000;

export function startToastExpiry(store: AppStore, timer: Timer, actions: UiActions): () => void {
  const scheduled = new Map<number, () => void>();
  const unsubscribe = store.subscribe((state) => {
    for (const toast of state.ui.toasts) {
      if (scheduled.has(toast.id)) continue;
      scheduled.set(
        toast.id,
        timer.after(TOAST_DURATION_MS, () => {
          scheduled.delete(toast.id);
          actions.dismissToast(toast.id);
        }),
      );
    }
  });
  return () => {
    unsubscribe();
    scheduled.forEach((cancel) => cancel());
  };
}
