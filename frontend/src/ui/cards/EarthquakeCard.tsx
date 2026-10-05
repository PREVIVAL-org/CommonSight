/**
 * Card for an earthquake: magnitude, depth, place and time (U-80).
 */
import type { EarthquakeView } from '../../domain/views/earthquake-view';
import { useTexts } from '../hooks';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';

export function EarthquakeCard({ view, variant }: { view: EarthquakeView; variant: CardVariant }) {
  const t = useTexts();
  return (
    <CardShell base={view.base} variant={variant} accent={null}>
      <dl className="facts">
        <div>
          <dt>{t.ui('card.magnitude')}</dt>
          <dd>{view.magnitude}</dd>
        </div>
        <div>
          <dt>{t.ui('card.depth')}</dt>
          <dd>{view.depth}</dd>
        </div>
        <div>
          <dt>{t.ui('card.place')}</dt>
          <dd>{view.place}</dd>
        </div>
      </dl>
    </CardShell>
  );
}
