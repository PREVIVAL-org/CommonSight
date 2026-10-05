/**
 * Picks the card by kind; if a kind has no card, the never check breaks the build (U-84).
 */
import type { ItemView } from '../../domain/views/item-view';
import type { CardVariant } from './card-parts';
import { EarthquakeCard } from './EarthquakeCard';
import { IndexCard } from './IndexCard';
import { MeasurementCard } from './MeasurementCard';
import { ModelValueCard } from './ModelValueCard';
import { NewsCard } from './NewsCard';
import { TrafficNoticeCard } from './TrafficNoticeCard';
import { WarningCard } from './WarningCard';

function unreachable(view: never): never {
  throw new Error(`no card for ${JSON.stringify(view)}`);
}

export function ItemCard({ view, variant }: { view: ItemView; variant: CardVariant }) {
  switch (view.kind) {
    case 'warning':
      return <WarningCard view={view} variant={variant} />;
    case 'measurement':
      return <MeasurementCard view={view} variant={variant} />;
    case 'modelValue':
      return <ModelValueCard view={view} variant={variant} />;
    case 'earthquake':
      return <EarthquakeCard view={view} variant={variant} />;
    case 'trafficNotice':
      return <TrafficNoticeCard view={view} variant={variant} />;
    case 'index':
      return <IndexCard view={view} variant={variant} />;
    case 'news':
      return <NewsCard view={view} variant={variant} />;
    default:
      return unreachable(view);
  }
}
