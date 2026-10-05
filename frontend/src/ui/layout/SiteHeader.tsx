/**
 * Header of the element (B-D1, U-01): logo of the host (or the CommonSight icon) with its link, the name of the
 * installation (attribute site-name, else CommonSight), a clock with date and the light/dark switch. The switch only appears when the host page sets no theme (T-05).
 */
import { Moon, Sun, SunMoon } from 'lucide-react';
import type { ThemeMode } from '../../theme/theme-mode';
import { useActions, useAppState, usePage, useTexts } from '../hooks';

const TIME = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
const DATE = new Intl.DateTimeFormat('de-DE', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});

/** Automatic (system setting), light, dark, and around again. */
const NEXT: Record<'auto' | ThemeMode, 'auto' | ThemeMode> = { auto: 'light', light: 'dark', dark: 'auto' };

function Logo() {
  const { branding } = usePage();
  const dark = useAppState((state, selectors) => selectors.themeMode(state) === 'dark');
  const image = (
    <img
      className="site-logo"
      src={dark && branding.logoDark !== null ? branding.logoDark : branding.logo}
      alt={branding.logoAlt}
      height={32}
    />
  );
  return branding.logoLink === null ? image : <a href={branding.logoLink}>{image}</a>;
}

function Clock() {
  const nowMs = useAppState((state) => state.env.clockMs);
  const now = new Date(nowMs);
  return (
    <div className="site-clock">
      <time className="site-clock-time" dateTime={now.toISOString()}>
        {TIME.format(now)}
      </time>
      <time className="site-clock-date" dateTime={now.toISOString().slice(0, 10)}>
        {DATE.format(now)}
      </time>
    </div>
  );
}

function ThemeSwitch() {
  const t = useTexts();
  const actions = useActions();
  const preset = useAppState((state) => state.env.themeAttribute);
  const mode = useAppState((state) => state.env.themeChoice ?? 'auto');
  if (preset !== null) return null;
  const next = NEXT[mode];
  const label = t.ui('header.theme', { mode: t.ui(`header.theme.${mode}`) });
  const Icon = mode === 'auto' ? SunMoon : mode === 'light' ? Sun : Moon;
  return (
    <button
      type="button"
      className="theme-switch"
      aria-label={label}
      title={t.ui('header.themeNext', { label, next: t.ui(`header.theme.${next}`) })}
      onClick={() => actions.chooseTheme(next === 'auto' ? null : next)}
    >
      <Icon size={20} aria-hidden="true" />
    </button>
  );
}

export function SiteHeader() {
  const { branding } = usePage();
  return (
    <header className="site-header" part="banner">
      <div className="site-brand">
        <Logo />
        <h1 className="site-title">{branding.name}</h1>
      </div>
      <Clock />
      <ThemeSwitch />
    </header>
  );
}
