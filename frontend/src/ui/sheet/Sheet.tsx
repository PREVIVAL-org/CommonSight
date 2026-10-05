/**
 * Side panel of the detail sheet as a Radix dialog with a portal in the shadow root (U-40 to U-44, T-03, T-09,
 * Architecture 9.5).
 */
import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { layerMeta } from '../../contract/master-data';
import type { LayerId } from '../../contract/types';
import type { SheetState } from '../../domain/selection';
import { useActions, useAppState, usePortalContainer, useTexts } from '../hooks';
import { ColorLegendSheet } from '../parts/ColorLegend';
import { focusIfLost, useFocusReturn } from './focus-return';
import { ItemSheet } from './ItemSheet';
import { LayerSheet } from './LayerSheet';
import { SourcesSheet } from './SourcesSheet';
import { UnassignedSheet } from './UnassignedSheet';

function SheetBody({ sheet }: { sheet: SheetState }) {
  switch (sheet.type) {
    case 'sources':
      return <SourcesSheet />;
    case 'layer':
      return <LayerSheet layer={sheet.layer} />;
    case 'item':
      return <ItemSheet layer={sheet.layer} itemId={sheet.itemId} />;
    case 'unassigned':
      return <UnassignedSheet />;
    case 'legend':
      return <ColorLegendSheet />;
  }
}

function useHeading(sheet: SheetState): { title: string; subtitle: string } {
  const t = useTexts();
  const country = useAppState((state, selectors) => selectors.countryName(state));
  const itemTitle = useAppState((state, selectors) =>
    sheet.type === 'item' ? (selectors.itemView(state, sheet.layer, sheet.itemId)?.base.title ?? null) : null,
  );
  const regionName = useAppState((state, selectors) => selectors.unassigned(state).regionName);
  // A layer for all countries (e.g. space weather) is not one of the selected country.
  const placeOf = (layer: LayerId): string =>
    layerMeta(layer).scope === 'global' ? t.ui('place.all') : country;
  switch (sheet.type) {
    case 'sources':
      return { title: t.ui('sheet.sources.title'), subtitle: t.ui('sheet.sources.subtitle', { country }) };
    case 'layer':
      return {
        title: t.layer(sheet.layer),
        subtitle: t.ui('sheet.layer.subtitle', { country: placeOf(sheet.layer) }),
      };
    case 'item':
      return {
        title: itemTitle ?? t.layer(sheet.layer),
        subtitle: t.ui('sheet.layer.subtitle', { country: placeOf(sheet.layer) }),
      };
    case 'unassigned':
      return { title: t.ui('sheet.unassigned.title'), subtitle: regionName };
    case 'legend':
      return { title: t.ui('legend.colors'), subtitle: country };
  }
}

function SheetPanel({ sheet }: { sheet: SheetState }) {
  const t = useTexts();
  const heading = useHeading(sheet);
  const focus = useFocusReturn();
  return (
    <Dialog.Content className="sheet" part="sheet" aria-describedby={undefined} {...focus}>
      <div className="sheet-head">
        <div>
          <Dialog.Title key={JSON.stringify(sheet)} ref={focusIfLost} tabIndex={-1} className="sheet-title">
            {heading.title}
          </Dialog.Title>
          <p className="sheet-subtitle">{heading.subtitle}</p>
        </div>
        <Dialog.Close className="icon-button" aria-label={t.ui('sheet.close')}>
          <X size={18} aria-hidden="true" />
        </Dialog.Close>
      </div>
      <div className="sheet-body">
        <SheetBody sheet={sheet} />
      </div>
    </Dialog.Content>
  );
}

/**
 * Not modal, because the Radix focus trap holds the focus in the shadow DOM (targets are retargeted to the host);
 * Escape, closing by clicking outside and returning the focus are preserved (README, decision F3).
 */
export function Sheet() {
  const actions = useActions();
  const container = usePortalContainer();
  const sheet = useAppState((state) => state.ui.sheet);
  if (container === null) return null;
  return (
    <Dialog.Root
      open={sheet !== null}
      modal={false}
      onOpenChange={(open) => (open ? undefined : actions.closeSheet())}
    >
      <Dialog.Portal container={container}>
        {sheet === null ? null : (
          <>
            <div className="sheet-overlay" aria-hidden="true" onClick={() => actions.closeSheet()} />
            <SheetPanel sheet={sheet} />
          </>
        )}
      </Dialog.Portal>
    </Dialog.Root>
  );
}
