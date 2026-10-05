/**
 * Card for a traffic notice: road, type, start and description; shortened when compact (U-80).
 */
import type { TrafficNoticeView } from '../../domain/views/traffic-notice-view';
import { useTexts } from '../hooks';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';

export function TrafficNoticeCard({ view, variant }: { view: TrafficNoticeView; variant: CardVariant }) {
  const t = useTexts();
  const facts: [string, string | null][] = [
    [t.ui('card.road'), view.road],
    [t.ui('card.noticeType'), view.noticeType],
    [t.ui('card.start'), view.start],
  ];
  const text = variant === 'compact' ? view.summary : view.description;
  return (
    <CardShell base={view.base} variant={variant} accent={null}>
      <dl className="facts">
        {facts
          .filter((fact): fact is [string, string] => fact[1] !== null)
          .map(([label, value]) => (
            <div key={label}>
              <dt>{label}</dt>
              <dd>{value}</dd>
            </div>
          ))}
      </dl>
      {text === null ? null : <p className="card-text">{text}</p>}
    </CardShell>
  );
}
