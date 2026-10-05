/**
 * Actions that take over environment values: connection, visibility, theme, embedding and clock.
 */
import type { ThemeMode } from '../theme/theme-mode';
import type { AppStore } from './patch';
import { patch } from './patch';

export interface EnvironmentActions {
  setOnline(online: boolean): void;
  setVisible(visible: boolean): void;
  setSystemDark(dark: boolean): void;
  setThemeAttribute(theme: ThemeMode | null): void;
  /** Own choice of the light/dark switch; null: follow the system setting */
  chooseTheme(theme: ThemeMode | null): void;
  setEmbedded(embedded: boolean): void;
  tickClock(nowMs: number): void;
  tickEvaluation(nowMs: number): void;
}

export function createEnvironmentActions(store: AppStore): EnvironmentActions {
  return {
    setOnline: (online) => patch(store, 'env', { online }),
    setVisible: (visible) => patch(store, 'env', { visible }),
    setSystemDark: (systemDark) => patch(store, 'env', { systemDark }),
    setThemeAttribute: (themeAttribute) => patch(store, 'env', { themeAttribute }),
    chooseTheme: (themeChoice) => patch(store, 'env', { themeChoice }),
    setEmbedded: (embedded) => patch(store, 'env', { embedded }),
    tickClock: (clockMs) => patch(store, 'env', { clockMs }),
    tickEvaluation: (evaluationMs) => patch(store, 'env', { evaluationMs, clockMs: evaluationMs }),
  };
}
