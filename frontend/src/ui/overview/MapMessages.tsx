/**
 * Map messages: basemap not available (K-12) and region outline loading or missing, with "Erneut versuchen"
 * (retry) (U-17).
 */
import { useActions, useAppState, useTexts } from '../hooks';

export function MapMessages() {
  const t = useTexts();
  const actions = useActions();
  const basemapMissing = useAppState((state) => state.map.tilesUrl === null || state.map.mapError);
  const outline = useAppState((state) => state.map.outline.state);
  return (
    <>
      {basemapMissing ? (
        <div className="map-overlay map-message" data-tone="error" role="alert">
          {t.ui('map.error')}
        </div>
      ) : null}
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
