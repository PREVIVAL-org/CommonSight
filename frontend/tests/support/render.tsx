/**
 * Renders components with store, selectors, texts and portal node as in the element, but without shadow DOM and
 * without network.
 */
import * as Tooltip from '@radix-ui/react-tooltip';
import { render } from '@testing-library/react';
import type { RenderResult } from '@testing-library/react';
import type { ReactElement } from 'react';
import { createSelectors } from '../../src/state/selectors';
import type { AppStoreBundle } from '../../src/state/store';
import { MapContainerContext, PortalContainerContext, ServicesContext } from '../../src/ui/services';
import { formats, t } from './deps';
import { createTestStore } from './store';

export function renderWithServices(
  ui: ReactElement,
  bundle: AppStoreBundle = createTestStore(),
): RenderResult & { bundle: AppStoreBundle } {
  const portal = document.createElement('div');
  document.body.append(portal);
  const mapContainer = document.createElement('div');
  const services = {
    ...bundle,
    selectors: createSelectors({ t, format: formats }, new Intl.Collator('de')),
    t,
  };
  const result = render(
    <ServicesContext.Provider value={services}>
      <PortalContainerContext.Provider value={portal}>
        <MapContainerContext.Provider value={mapContainer}>
          <Tooltip.Provider>{ui}</Tooltip.Provider>
        </MapContainerContext.Provider>
      </PortalContainerContext.Provider>
    </ServicesContext.Provider>,
  );
  return { ...result, bundle };
}
