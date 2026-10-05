/**
 * Map tools: reset view, my location, fullscreen (K-02, K-14); zoom and scale are provided by the map itself.
 */
import { House, LocateFixed, Maximize } from 'lucide-react';
import { useActions, useAppState, useTexts } from '../hooks';
import { IconButton } from '../parts/IconButton';

export function MapTools() {
  const t = useTexts();
  const actions = useActions();
  const regional = useAppState((state) => state.selection.regionId !== null);
  return (
    <div
      className="map-overlay map-tools"
      role="toolbar"
      aria-label={t.ui('map.tools')}
      aria-orientation="vertical"
    >
      <IconButton
        label={t.ui(regional ? 'map.resetRegion' : 'map.resetCountry')}
        onClick={() => actions.request('resetView')}
      >
        <House size={18} aria-hidden="true" />
      </IconButton>
      <IconButton label={t.ui('map.locate')} onClick={() => actions.request('locate')}>
        <LocateFixed size={18} aria-hidden="true" />
      </IconButton>
      <IconButton label={t.ui('map.fullscreen')} onClick={() => actions.request('fullscreen')}>
        <Maximize size={18} aria-hidden="true" />
      </IconButton>
    </div>
  );
}
