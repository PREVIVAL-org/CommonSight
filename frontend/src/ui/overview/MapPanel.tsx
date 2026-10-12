/**
 * Map area of the map view: map with notice, tools, messages and legend (K-01 to K-14, U-23 to U-25).
 */
import { MapLegend } from './MapLegend';
import { MapMessages, MapUnavailable } from './MapMessages';
import { MapNotice } from './MapNotice';
import { MapSlot } from './MapSlot';
import { MapTools } from './MapTools';

export function MapPanel() {
  return (
    <section className="panel map-panel overview-map" part="map">
      <div className="map-stage">
        <MapSlot />
        {/* Notice and "map unavailable" stack at the top, between the map tools and the zoom buttons. */}
        <div className="map-overlay map-top">
          <MapNotice />
          <MapUnavailable />
        </div>
        <MapTools />
        <MapMessages />
      </div>
      <MapLegend />
    </section>
  );
}
