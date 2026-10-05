/**
 * Card for a measurement: value with unit and reference, measured quantity, assessment with color and label or
 * rationale, measurement time, original level, additional values (U-80).
 */
import type { MeasurementView } from '../../domain/views/measurement-view';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';
import { FactList, LevelBadge } from './value-parts';

export function MeasurementCard({ view, variant }: { view: MeasurementView; variant: CardVariant }) {
  const { assessment } = view;
  return (
    <CardShell base={view.base} variant={variant} accent={assessment.badge.color}>
      <div>
        <div className="card-value">{view.value}</div>
        <div className="card-sub">
          {view.quantity} · {view.reference}
        </div>
      </div>
      <LevelBadge badge={assessment.badge} />
      {assessment.previous === null ? null : <p className="card-sub">{assessment.previous}</p>}
      {variant === 'full' ? (
        <>
          <p className="card-text">{assessment.basis}</p>
          <p className="card-sub">{assessment.origin}</p>
        </>
      ) : null}
      {assessment.sourceValue === null ? null : <p className="card-sub">{assessment.sourceValue}</p>}
      <p className="card-sub">{view.measuredAt}</p>
      {variant === 'full' ? <FactList facts={view.facts} /> : null}
    </CardShell>
  );
}
