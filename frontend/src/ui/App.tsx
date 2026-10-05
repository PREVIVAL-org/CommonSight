/**
 * Overall UI structure: the header with logo, name, clock and light/dark switch (unless the host page has its own),
 * one row with country/region selection and views as tabs, detail sheet, status messages; no footer (U-01 to U-07).
 * A visitor the start page does not admit sees the header and the members card only (A-D2).
 */
import * as Tabs from '@radix-ui/react-tabs';
import * as Tooltip from '@radix-ui/react-tooltip';
import { ListChecks, MapPinned, TriangleAlert } from 'lucide-react';
import { LIST_VIEWS } from '../domain/layer-slots';
import type { ViewId } from '../domain/selection';
import { APP_VERSION } from '../version';
import { useActions, useAppState, usePage, useTexts } from './hooks';
import { BorderToggle } from './layout/BorderToggle';
import { MembersCard } from './layout/MembersCard';
import { OfflineBanner } from './layout/OfflineBanner';
import { PlacePicker } from './layout/PlacePicker';
import { SiteHeader } from './layout/SiteHeader';
import { Toasts } from './layout/Toasts';
import { ListView } from './lists/ListView';
import { NoticeToggle } from './overview/MapNotice';
import { OverviewView } from './overview/OverviewView';
import { Sheet } from './sheet/Sheet';

/** Source code of CommonSight, linked in the footer. */
const REPOSITORY_URL = 'https://github.com/PREVIVAL-org/CommonSight';

/** The CommonSight glyph (as custom/logo.svg), inline: an installation's own logo does not replace it. */
function Glyph() {
  return (
    <svg className="app-glyph" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
      <polygon points="32,6 58,58 6,58" fill="#ff7500" />
    </svg>
  );
}

/** The overview and the list views that have layers (a list view without a layer package gets no tab). */
const VIEWS: readonly { id: ViewId; icon: typeof MapPinned }[] = [
  { id: 'overview', icon: MapPinned },
  { id: 'measurements', icon: ListChecks },
  { id: 'events', icon: TriangleAlert },
].filter(({ id }) => id === 'overview' || (LIST_VIEWS as readonly string[]).includes(id)) as {
  id: ViewId;
  icon: typeof MapPinned;
}[];

function ViewTabList() {
  const t = useTexts();
  return (
    <Tabs.List className="view-tabs" aria-label={t.ui('view.tabs')}>
      {VIEWS.map(({ id, icon: Icon }) => (
        <Tabs.Trigger key={id} value={id} className="view-tab">
          <Icon size={16} aria-hidden="true" /> {t.ui(`view.${id}`)}
        </Tabs.Trigger>
      ))}
    </Tabs.List>
  );
}

function Views() {
  const actions = useActions();
  const view = useAppState((state) => state.selection.view);
  const change = (value: string): void => actions.setView(value as ViewId);
  return (
    <Tabs.Root value={view} onValueChange={change} activationMode="manual">
      <div className="toolbar-row" part="header">
        <PlacePicker />
        <NoticeToggle />
        <BorderToggle />
        <ViewTabList />
      </div>
      <Tabs.Content value="overview">
        <OverviewView />
      </Tabs.Content>
      <Tabs.Content value="measurements">
        <ListView view="measurements" />
      </Tabs.Content>
      <Tabs.Content value="events">
        <ListView view="events" />
      </Tabs.Content>
    </Tabs.Root>
  );
}

/** A visitor the start page does not admit: header and members card only (A-D2). */
function Closed() {
  const page = usePage();
  return (
    <div className="app" aria-label={page.branding.name} role="region">
      {page.header ? <SiteHeader /> : null}
      <div className="main">
        <div className="members">
          <MembersCard />
        </div>
      </div>
    </div>
  );
}

export function App() {
  const t = useTexts();
  const page = usePage();
  if (!page.access.admitted) return <Closed />;
  return (
    <Tooltip.Provider delayDuration={300}>
      <div className="app" aria-label={page.branding.name} role="region">
        {page.header ? <SiteHeader /> : null}
        <OfflineBanner />
        {/* No <main>: the element sits inside the host page, which has its own. */}
        <div className="main">
          <Views />
        </div>
        {/* Only "Powered by CommonSight" with the version, right-aligned and small; no footer bar with notices (U-04). */}
        <p className="app-version">
          {t.ui('app.poweredBy')}{' '}
          <a className="app-powered" href={REPOSITORY_URL} target="_blank" rel="noopener noreferrer">
            <Glyph />
            {t.ui('app.poweredByName')}
          </a>{' '}
          {t.ui('app.version', { version: APP_VERSION })}
        </p>
        <Sheet />
        <Toasts />
      </div>
    </Tooltip.Provider>
  );
}
