/**
 * Transfers the browser's visibility, online state and system theme into the store (U-06, U-51, T-05).
 */
import type { PageEnvironment } from '../infrastructure/page-visibility';
import type { SystemTheme } from '../infrastructure/system-theme';
import type { EnvironmentActions } from '../state/environment-actions';

export function startEnvironmentSync(
  page: PageEnvironment,
  theme: SystemTheme,
  actions: EnvironmentActions,
): () => void {
  const stops = [
    page.onVisibilityChange((visible) => actions.setVisible(visible)),
    page.onOnlineChange((online) => actions.setOnline(online)),
    theme.onChange((dark) => actions.setSystemDark(dark)),
  ];
  return () => stops.forEach((stop) => stop());
}
