/**
 * Map notice above the map, from the layer with the highest notice priority (today the warning situation); when it
 * has entries, with "Anzeigen" (show), which switches to the list view of the layer; can be closed and shown again
 * (U-23).
 * Closed, it shows again via a button on the map (desktop) or via "i" next to the country selector (mobile).
 */
import { Info, TriangleAlert, X } from 'lucide-react';
import { NOTICE_SLOT } from '../../domain/layer-slots';
import type { MapNoticeView } from '../../domain/views/map-notice-view';
import { useActions, useAppState, useTexts } from '../hooks';
import { IconButton } from '../parts/IconButton';

function ShowNoticeButton() {
  const t = useTexts();
  const actions = useActions();
  return (
    <button
      type="button"
      className="map-overlay map-notice map-notice-show button"
      onClick={() => actions.setMapNoticeHidden(false)}
    >
      <Info size={14} aria-hidden="true" /> {t.ui('notice.show')}
    </button>
  );
}

/**
 * "i" in the toolbar row: on narrow screens the closed notice shows again from here instead of covering the map;
 * only in the map view. Hidden on wide screens by CSS.
 */
export function NoticeToggle() {
  const t = useTexts();
  const actions = useActions();
  const visible = useAppState(
    (state) => NOTICE_SLOT !== null && state.selection.mapNoticeHidden && state.selection.view === 'overview',
  );
  if (!visible) return null;
  return (
    <span className="notice-toggle">
      <IconButton label={t.ui('notice.show')} onClick={() => actions.setMapNoticeHidden(false)}>
        <Info size={18} aria-hidden="true" />
      </IconButton>
    </span>
  );
}

/** "Anzeigen": switches to the list view with the entries of the layer. */
function ShowEntriesLink({ notice }: { notice: MapNoticeView }) {
  const t = useTexts();
  const actions = useActions();
  return (
    <button
      type="button"
      className="button button-link"
      aria-label={notice.openLabel}
      onClick={() => actions.openEntries(notice.layer)}
    >
      {t.ui('notice.showEntries')}
    </button>
  );
}

function CloseNoticeButton() {
  const t = useTexts();
  const actions = useActions();
  return (
    <button
      type="button"
      className="icon-button"
      aria-label={t.ui('notice.close')}
      onClick={() => actions.setMapNoticeHidden(true)}
    >
      <X size={14} aria-hidden="true" />
    </button>
  );
}

export function MapNotice() {
  const hidden = useAppState((state) => state.selection.mapNoticeHidden);
  const notice = useAppState((state, selectors) => selectors.mapNotice(state));
  if (notice === null) return null;
  if (hidden) return <ShowNoticeButton />;
  return (
    <div className="map-overlay map-notice" data-tone={notice.tone} role="status">
      <TriangleAlert size={16} aria-hidden="true" className="map-notice-icon" />
      {/* "Anzeigen" sits inline in the text, not as a separate column. */}
      <span className="map-notice-text">
        {notice.text}
        {notice.hasEntries ? (
          <>
            {' · '}
            <ShowEntriesLink notice={notice} />
          </>
        ) : null}
      </span>
      <CloseNoticeButton />
    </div>
  );
}
