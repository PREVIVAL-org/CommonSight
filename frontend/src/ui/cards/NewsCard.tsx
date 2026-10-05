/**
 * Card for a news item: topic, time, headline and feed name (U-80).
 */
import type { NewsView } from '../../domain/views/news-view';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';

export function NewsCard({ view, variant }: { view: NewsView; variant: CardVariant }) {
  return (
    <CardShell base={view.base} variant={variant} accent={null}>
      <span className="card-sub">{view.feed}</span>
    </CardShell>
  );
}
