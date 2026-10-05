/**
 * Card for a warning: level with fixed color, warning type, area, validity, text in sections; when compact only
 * the first section, shortened (U-80).
 */
import type { WarningView } from '../../domain/views/warning-view';
import type { CardVariant } from './card-parts';
import { CardShell } from './card-parts';
import { LevelBadge, ValidityRange } from './value-parts';

export function WarningCard({ view, variant }: { view: WarningView; variant: CardVariant }) {
  return (
    <CardShell base={view.base} variant={variant} accent={view.badge.color}>
      <div className="card-header">
        <LevelBadge badge={view.badge} />
        <span>{view.hazard}</span>
      </div>
      <p className="card-sub">{view.area}</p>
      <ValidityRange validity={view.validity} />
      {variant === 'compact' ? (
        view.summary === null ? null : (
          <p className="card-text">{view.summary}</p>
        )
      ) : (
        <div className="sections">
          {view.sections.map((section, index) => (
            <section key={`${section.heading}-${index}`}>
              <h4>{section.heading}</h4>
              <p className="card-text">{section.text}</p>
            </section>
          ))}
        </div>
      )}
    </CardShell>
  );
}
