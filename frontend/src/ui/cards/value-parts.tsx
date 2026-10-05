/**
 * Shared display parts for values: badge with fixed color, validity, additional values, scale and history
 * (9.8, T-07, T-08).
 */
import type { CSSProperties } from 'react';
import type { BadgeView } from '../../domain/views/badge-view';
import type { FactView } from '../../domain/views/fact-view';

export function LevelBadge({ badge }: { badge: BadgeView }) {
  const style = badge.color === null ? undefined : ({ '--badge-color': badge.color } as CSSProperties);
  return (
    <span className="badge" data-dashed={badge.dashed} style={style}>
      <span className="badge-dot" aria-hidden="true" />
      {badge.label}
    </span>
  );
}

export function ValidityRange({ validity }: { validity: string | null }) {
  return validity === null ? null : <p className="card-sub">{validity}</p>;
}

export function FactList({ facts }: { facts: FactView[] }) {
  if (facts.length === 0) return null;
  return (
    <dl className="facts">
      {facts.map((fact) => (
        <div key={fact.label}>
          <dt>{fact.label}</dt>
          <dd>{fact.value}</dd>
        </div>
      ))}
    </dl>
  );
}

export function ScaleBar({ ratio, label }: { ratio: number; label: string }) {
  return (
    <div className="scale-bar" role="img" aria-label={label}>
      <div className="scale-bar-fill" style={{ width: `${Math.round(ratio * 100)}%` }} />
    </div>
  );
}

const SPARK_WIDTH = 240;
const SPARK_HEIGHT = 36;

/** History as a line; `max` is the upper end of the scale, so equal values sit at the same height. */
export function Sparkline({ values, max, label }: { values: number[]; max: number; label: string }) {
  if (values.length < 2 || max <= 0) return null;
  const step = SPARK_WIDTH / (values.length - 1);
  const points = values.map(
    (value, index) =>
      `${(index * step).toFixed(1)},${(SPARK_HEIGHT - (Math.min(value, max) / max) * (SPARK_HEIGHT - 4) - 2).toFixed(1)}`,
  );
  return (
    <svg
      className="sparkline"
      viewBox={`0 0 ${SPARK_WIDTH} ${SPARK_HEIGHT}`}
      role="img"
      aria-label={label}
      preserveAspectRatio="none"
    >
      <polyline
        points={points.join(' ')}
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
}
