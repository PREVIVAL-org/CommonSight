/**
 * Reports the tab's visibility and the browser's online state (U-06, U-51).
 */

export type Unsubscribe = () => void;

export interface PageEnvironment {
  isVisible(): boolean;
  isOnline(): boolean;
  onVisibilityChange(listener: (visible: boolean) => void): Unsubscribe;
  onOnlineChange(listener: (online: boolean) => void): Unsubscribe;
}

export function createPageEnvironment(win: Window): PageEnvironment {
  const doc = win.document;
  return {
    isVisible: () => !doc.hidden,
    isOnline: () => win.navigator.onLine,
    onVisibilityChange: (listener) => {
      const handler = (): void => listener(!doc.hidden);
      doc.addEventListener('visibilitychange', handler);
      return () => doc.removeEventListener('visibilitychange', handler);
    },
    onOnlineChange: (listener) => {
      const online = (): void => listener(true);
      const offline = (): void => listener(false);
      win.addEventListener('online', online);
      win.addEventListener('offline', offline);
      return () => {
        win.removeEventListener('online', online);
        win.removeEventListener('offline', offline);
      };
    },
  };
}
