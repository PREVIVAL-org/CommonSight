/**
 * Switches the element into fullscreen mode and back (K-02).
 */

export type FullscreenResult = 'entered' | 'exited' | 'unsupported';

export interface Fullscreen {
  toggle(): Promise<FullscreenResult>;
}

export function createFullscreen(element: HTMLElement): Fullscreen {
  const doc = element.ownerDocument;
  return {
    toggle: async () => {
      if (doc.fullscreenElement === element) {
        await doc.exitFullscreen();
        return 'exited';
      }
      if (!doc.fullscreenEnabled || typeof element.requestFullscreen !== 'function') return 'unsupported';
      await element.requestFullscreen();
      return 'entered';
    },
  };
}
