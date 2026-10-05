/**
 * Executes the device commands "Mein Standort" (my location) and "Vollbild" (fullscreen) and reports the
 * result as a toast (K-02, U-05).
 */
import type { Fullscreen } from '../infrastructure/fullscreen';
import type { Locator } from '../infrastructure/geolocation';
import { LocationError } from '../infrastructure/geolocation';
import type { AppActions, AppStore } from '../state/store';

export interface DeviceCommandDeps {
  store: AppStore;
  actions: AppActions;
  locator: Locator;
  fullscreen: Fullscreen;
}

async function locate(deps: DeviceCommandDeps): Promise<void> {
  try {
    const position = await deps.locator.locate();
    deps.actions.focusPoint(position.lon, position.lat);
    deps.actions.pushToast('toast.located', 'info');
  } catch (error) {
    const unsupported = error instanceof LocationError && error.reason === 'unsupported';
    deps.actions.pushToast(unsupported ? 'toast.locateUnsupported' : 'toast.locateFailed', 'error');
  }
}

async function toggleFullscreen(deps: DeviceCommandDeps): Promise<void> {
  try {
    const result = await deps.fullscreen.toggle();
    if (result === 'unsupported') deps.actions.pushToast('toast.fullscreenUnsupported', 'error');
  } catch {
    // The browser refused fullscreen: report it like "not supported" (K-02, fallback message).
    deps.actions.pushToast('toast.fullscreenUnsupported', 'error');
  }
}

export function startDeviceCommands(deps: DeviceCommandDeps): () => void {
  return deps.store.subscribe((state, previous) => {
    if (state.ui.commands.locate !== previous.ui.commands.locate) void locate(deps);
    if (state.ui.commands.fullscreen !== previous.ui.commands.fullscreen) void toggleFullscreen(deps);
  });
}
