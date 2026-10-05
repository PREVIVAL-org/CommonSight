/**
 * Attaches the map container created outside React, so the map is preserved when the view changes.
 */
import { useContext, useEffect, useRef } from 'react';
import { MapContainerContext } from '../services';

export function MapSlot() {
  const container = useContext(MapContainerContext);
  const slot = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const host = slot.current;
    if (host === null || container === null) return undefined;
    host.append(container);
    return () => container.remove();
  }, [container]);
  // No landmark of its own: the map canvas inside is the region "Interaktive Karte für …" (MapLibre, map-view).
  return <div className="map-canvas-slot" ref={slot} />;
}
