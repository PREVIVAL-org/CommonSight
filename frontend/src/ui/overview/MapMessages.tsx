/**
 * Map messages: basemap not available (K-12) at the top, with a hint to switch on hardware acceleration when WebGL2
 * is missing; region outline loading or missing at the bottom, with "Erneut versuchen" (retry) (U-17).
 */
import { useActions, useAppState, useTexts } from '../hooks';

/** Placed in the top stack under the map notice: without a map, the top is where the eye looks first. */
export function MapUnavailable() {
  const t = useTexts();
  const mapError = useAppState((state) => state.map.mapError);
  const basemapMissing = useAppState((state) => state.map.tilesUrl === null) || mapError !== null;
  if (!basemapMissing) return null;
  return (
    <div className="map-message" data-tone="error" role="alert">
      {t.ui(mapError === 'no-webgl' ? 'map.noWebgl' : 'map.error')}
    </div>
  );
}

export function MapMessages() {
  const t = useTexts();
  const actions = useActions();
  const outline = useAppState((state) => state.map.outline.state);
  return (
    <>
      {outline === 'loading' ? (
        <div className="map-overlay map-message" role="status">
          {t.ui('map.outlineLoading')}
        </div>
      ) : null}
      {outline === 'error' ? (
        <div className="map-overlay map-message" data-tone="error" role="alert">
          {t.ui('map.outlineError')}
          <button type="button" className="button" onClick={() => actions.request('retryOutline')}>
            {t.ui('map.retry')}
          </button>
        </div>
      ) : null}
    </>
  );
}
