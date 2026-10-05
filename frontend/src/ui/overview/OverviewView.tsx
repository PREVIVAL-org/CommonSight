/**
 * View "Lagekarte" (map view): layer list, map, news and key figures (U-02).
 */
import { LayerPanel } from './LayerPanel';
import { MapPanel } from './MapPanel';
import { MetricTiles } from './MetricTiles';
import { NewsPanel } from './NewsPanel';

export function OverviewView() {
  return (
    <div className="overview">
      {/* Layers, map and news in one row of equal height; the layer list sets the height. */}
      <div className="overview-row">
        <LayerPanel />
        <MapPanel />
        <NewsPanel />
      </div>
      <MetricTiles />
    </div>
  );
}
