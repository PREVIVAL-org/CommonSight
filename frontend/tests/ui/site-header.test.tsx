// @vitest-environment jsdom
/**
 * Header of the element and the members card (ACCESS-AND-BRANDING B-D1, B-D2, A-D2): logo of the host page with
 * link, dark variant, light/dark switch only without a preset of the host, header off, members card instead of the map.
 */
import { cleanup, fireEvent, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import { App } from '../../src/ui/App';
import type { PageSettings } from '../../src/ui/services';
import { DEFAULT_PAGE, PageContext } from '../../src/ui/services';
import { renderWithServices } from '../support/render';
import { createTestStore } from '../support/store';

afterEach(cleanup);

const BRANDED: PageSettings = {
  ...DEFAULT_PAGE,
  branding: {
    name: 'BEISPIEL',
    logo: 'https://example.org/custom/logo.svg',
    logoDark: 'https://example.org/custom/logo-dark.svg',
    logoLink: 'https://example.org/',
    logoAlt: 'Beispiel e.V.',
  },
};

function withPage(page: PageSettings, ui: ReactElement = <App />): ReactElement {
  return <PageContext.Provider value={page}>{ui}</PageContext.Provider>;
}

describe('header of the element', () => {
  it('shows the logo of the host page with its link, and its dark variant in the dark theme', () => {
    const { bundle } = renderWithServices(withPage(BRANDED));
    const logo = screen.getByRole('img', { name: 'Beispiel e.V.' });
    expect(logo).toHaveAttribute('src', 'https://example.org/custom/logo.svg');
    expect(logo.closest('a')).toHaveAttribute('href', 'https://example.org/');
    fireEvent.click(screen.getByRole('button', { name: 'Darstellung: Automatisch' }));
    fireEvent.click(screen.getByRole('button', { name: 'Darstellung: Hell' }));
    expect(bundle.store.getState().env.themeChoice).toBe('dark');
    expect(screen.getByRole('img', { name: 'Beispiel e.V.' })).toHaveAttribute(
      'src',
      'https://example.org/custom/logo-dark.svg',
    );
    fireEvent.click(screen.getByRole('button', { name: 'Darstellung: Dunkel' }));
    expect(bundle.store.getState().env.themeChoice).toBeNull();
  });

  it('shows the name of the installation (site-name), else CommonSight', () => {
    renderWithServices(withPage(BRANDED));
    expect(screen.getByRole('heading', { level: 1, name: 'BEISPIEL' })).toBeInTheDocument();
    cleanup();
    renderWithServices(withPage(DEFAULT_PAGE));
    expect(screen.getByRole('heading', { level: 1, name: 'CommonSight' })).toBeInTheDocument();
  });

  it('has no light/dark switch when the host page sets the theme (T-05)', () => {
    const store = createTestStore();
    store.actions.setThemeAttribute('dark');
    renderWithServices(withPage(DEFAULT_PAGE), store);
    expect(screen.getByRole('banner')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Darstellung/ })).not.toBeInTheDocument();
  });

  it('is left out with header="none" for a host page with its own (T-10)', () => {
    renderWithServices(withPage({ ...DEFAULT_PAGE, header: false }));
    expect(screen.queryByRole('banner')).not.toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Land / Region' })).toBeInTheDocument();
  });
});

describe('members card', () => {
  const DENIED: PageSettings['access'] = {
    admitted: false,
    community: 'PREVIVAL.org',
    loginUrl: 'https://previval.org/index.php?login/',
    registerUrl: 'https://previval.org/index.php?register/',
  };

  it('names the installation, else CommonSight', () => {
    renderWithServices(withPage({ ...BRANDED, access: DENIED }));
    expect(
      screen.getByRole('heading', { level: 2, name: 'BEISPIEL ist für Mitglieder von PREVIVAL.org' }),
    ).toBeInTheDocument();
    cleanup();
    renderWithServices(withPage({ ...DEFAULT_PAGE, access: DENIED }));
    expect(
      screen.getByRole('heading', { level: 2, name: 'CommonSight ist für Mitglieder von PREVIVAL.org' }),
    ).toBeInTheDocument();
  });

  it('replaces the map for a visitor the start page does not admit, with the links of the community', () => {
    renderWithServices(withPage({ ...DEFAULT_PAGE, access: DENIED }));
    expect(screen.getByRole('banner')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Anmelden' })).toHaveAttribute(
      'href',
      'https://previval.org/index.php?login/',
    );
    expect(screen.getByRole('link', { name: 'Kostenlos registrieren' })).toHaveAttribute(
      'href',
      'https://previval.org/index.php?register/',
    );
    expect(screen.queryByRole('combobox', { name: 'Land / Region' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab')).not.toBeInTheDocument();
  });
});
