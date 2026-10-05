/**
 * UI actions: detail sheet, toasts and commands to flow control and map.
 */
import type { Msg } from '../contract/types';
import type { SheetState } from '../domain/selection';
import type { UiTextKey } from '../i18n/ui-texts.de';
import type { CommandName, Toast } from './app-state';
import type { AppStore } from './patch';
import { patch } from './patch';

export interface UiActions {
  openSheet(sheet: SheetState): void;
  closeSheet(): void;
  pushToast(key: UiTextKey, tone: Toast['tone'], params?: ToastParams): void;
  /** A short notice with a message of a layer package (e.g. new warnings). */
  pushMessage(message: Msg, tone: Toast['tone']): void;
  dismissToast(id: number): void;
  /** Requests a command (location, fullscreen, reset view, reload outline). */
  request(command: CommandName): void;
}

/** Maximum number of toasts visible at the same time. */
const MAX_TOASTS = 3;

type ToastParams = Readonly<Record<string, string | number>>;
type ToastText = { key: UiTextKey; params?: ToastParams } | { message: Msg };

function push(store: AppStore, id: number, tone: Toast['tone'], text: ToastText): void {
  patch(store, 'ui', (ui) => {
    const toast: Toast = { id, tone, ...text };
    return { toasts: [...ui.toasts, toast].slice(-MAX_TOASTS) };
  });
}

export function createUiActions(store: AppStore): UiActions {
  // Ids only ever go up: a reused id would let the expiry timer of a dismissed toast close the next one.
  let lastToastId = 0;
  return {
    openSheet: (sheet) => patch(store, 'ui', { sheet }),
    closeSheet: () => patch(store, 'ui', { sheet: null }),
    pushToast: (key, tone, params) =>
      push(store, ++lastToastId, tone, params === undefined ? { key } : { key, params }),
    pushMessage: (message, tone) => push(store, ++lastToastId, tone, { message }),
    dismissToast: (id) =>
      patch(store, 'ui', (ui) => ({ toasts: ui.toasts.filter((toast) => toast.id !== id) })),
    request: (command) =>
      patch(store, 'ui', (ui) => ({ commands: { ...ui.commands, [command]: ui.commands[command] + 1 } })),
  };
}
