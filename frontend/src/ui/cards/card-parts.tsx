/**
 * Parts shared by all cards, in the same position: header, title, source, "Auf Karte anzeigen" (show on map)
 * and frame (U-81, 9.8).
 */
import { MapPin } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import type { CardBaseView } from '../../domain/views/card-base-view';
import { useActions, useTexts } from '../hooks';
import { ExternalLink } from '../parts/ExternalLink';

export type CardVariant = 'compact' | 'full';

export function CardHeader({ base }: { base: CardBaseView }) {
  return (
    <div className="card-header">
      <span className="card-category">{base.category}</span>
      {base.timeIso === null ? <span>{base.time}</span> : <time dateTime={base.timeIso}>{base.time}</time>}
    </div>
  );
}

export function CardTitle({ base, variant }: { base: CardBaseView; variant: CardVariant }) {
  const actions = useActions();
  const t = useTexts();
  if (variant === 'full')
    return (
      <h3 className="card-title" lang={base.lang ?? undefined}>
        {base.title}
      </h3>
    );
  return (
    <h3 className="card-title" lang={base.lang ?? undefined}>
      <button
        type="button"
        className="card-title-button"
        aria-label={t.ui('card.details', { title: base.title })}
        onClick={() => actions.openSheet({ type: 'item', layer: base.layer, itemId: base.id })}
      >
        {base.title}
      </button>
    </h3>
  );
}

export function SourceLink({ base }: { base: CardBaseView }) {
  const t = useTexts();
  return (
    <ExternalLink href={base.url}>
      {t.ui('card.openSource')} · {base.source}
    </ExternalLink>
  );
}

export function ShowOnMapButton({ base }: { base: CardBaseView }) {
  const actions = useActions();
  const t = useTexts();
  if (!base.hasLocation) return null;
  return (
    <button
      type="button"
      className="button button-link"
      onClick={() => actions.showOnMap(base.layer, base.id)}
    >
      <MapPin size={14} aria-hidden="true" focusable="false" /> {t.ui('card.showOnMap')}
    </button>
  );
}

interface CardShellProps {
  base: CardBaseView;
  variant: CardVariant;
  accent: string | null;
  children: ReactNode;
}

/** Frame of every card: same arrangement of the shared parts, kind-specific content in between. */
export function CardShell({ base, variant, accent, children }: CardShellProps) {
  const t = useTexts();
  const style = accent === null ? undefined : ({ '--card-accent': accent } as CSSProperties);
  return (
    <article className="card" part="card" data-variant={variant} data-layer={base.layer} style={style}>
      <CardHeader base={base} />
      <CardTitle base={base} variant={variant} />
      {base.lang !== null && variant === 'full' ? (
        <span className="card-sub">{t.ui('card.language', { lang: base.lang })}</span>
      ) : null}
      {children}
      <div className="card-footer">
        <SourceLink base={base} />
        <ShowOnMapButton base={base} />
      </div>
    </article>
  );
}
