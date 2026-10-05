/**
 * Card for an index: value on its scale (e.g. Kp 5 of 9) and history as a line (U-80).
 */
import type { IndexView } from '../../domain/views/index-view';
import { useTexts } from '../hooks';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';
import { ScaleBar, Sparkline } from './value-parts';

export function IndexCard({ view, variant }: { view: IndexView; variant: CardVariant }) {
  const t = useTexts();
  return (
    <CardShell base={view.base} variant={variant} accent={null}>
      <div>
        <div className="card-value">{view.value}</div>
        <div className="card-sub">
          {view.name} · {view.scaleText} ({view.scaleName})
        </div>
      </div>
      <ScaleBar ratio={view.ratio} label={view.scaleText} />
      <Sparkline values={view.history} max={view.max} label={t.ui('card.history')} />
      {variant === 'full' ? <p className="card-text">{view.description}</p> : null}
    </CardShell>
  );
}
