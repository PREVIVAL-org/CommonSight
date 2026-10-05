/**
 * Banner shown when there is no internet connection (U-06).
 */
import { WifiOff } from 'lucide-react';
import { useAppState, useTexts } from '../hooks';

export function OfflineBanner() {
  const t = useTexts();
  const online = useAppState((state) => state.env.online);
  if (online) return null;
  return (
    <div className="offline-banner" role="alert">
      <WifiOff size={16} aria-hidden="true" /> {t.ui('offline.banner')}
    </div>
  );
}
