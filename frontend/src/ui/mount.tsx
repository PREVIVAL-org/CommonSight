/**
 * Mounts the React UI into a node of the shadow root and provides the contexts; returns the unmount function.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import type { PageSettings, UiServices } from './services';
import { MapContainerContext, PageContext, PortalContainerContext, ServicesContext } from './services';

export interface MountTargets {
  root: HTMLElement;
  portal: HTMLElement;
  mapContainer: HTMLElement;
}

export function mountUi(targets: MountTargets, services: UiServices, page: PageSettings): () => void {
  const root = createRoot(targets.root);
  root.render(
    <StrictMode>
      <ServicesContext.Provider value={services}>
        <PageContext.Provider value={page}>
          <PortalContainerContext.Provider value={targets.portal}>
            <MapContainerContext.Provider value={targets.mapContainer}>
              <App />
            </MapContainerContext.Provider>
          </PortalContainerContext.Provider>
        </PageContext.Provider>
      </ServicesContext.Provider>
    </StrictMode>,
  );
  return () => root.unmount();
}
