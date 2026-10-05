/**
 * Card for a model value: value with unit, short text, additional values and the hint
 * "Modellwert, keine Messung" (model value, not a measurement) (U-80).
 */
import type { ModelValueView } from '../../domain/views/model-value-view';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';
import { FactList } from './value-parts';

export function ModelValueCard({ view, variant }: { view: ModelValueView; variant: CardVariant }) {
  return (
    <CardShell base={view.base} variant={variant} accent={null}>
      <div>
        <div className="card-value">{view.value}</div>
        <div className="card-sub">{view.summary}</div>
      </div>
      <FactList facts={view.facts} />
      <p className="card-sub">{view.modelHint}</p>
    </CardShell>
  );
}
