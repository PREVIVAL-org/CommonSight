/**
 * Shown instead of the map when the start page admits only members of a community and the visitor is not one (A-D2):
 * what the map offers, and the way to log in or register there. The links come from the provider via gate.php.
 */
import { LockKeyhole } from 'lucide-react';
import { usePage, useTexts } from '../hooks';

/** Log in and register at the community, as far as its provider names the links. */
function MembersActions() {
  const t = useTexts();
  const { access } = usePage();
  return (
    <div className="members-actions">
      {access.loginUrl === null ? null : (
        <a className="members-button primary" href={access.loginUrl}>
          {t.ui('members.login')}
        </a>
      )}
      {access.registerUrl === null ? null : (
        <a className="members-button secondary" href={access.registerUrl}>
          {t.ui('members.register')}
        </a>
      )}
    </div>
  );
}

export function MembersCard() {
  const t = useTexts();
  const { access, branding } = usePage();
  const community = access.community !== '' ? access.community : t.ui('members.communityFallback');
  const title = t.ui('members.title', { name: branding.name, community });
  return (
    <section className="members-card" aria-labelledby="members-title">
      <div className="members-icon" aria-hidden="true">
        <LockKeyhole size={28} />
      </div>
      <h2 id="members-title">{title}</h2>
      <p>{t.ui('members.text', { community })}</p>
      <ul className="members-list">
        <li>{t.ui('members.offer.map')}</li>
        <li>{t.ui('members.offer.border')}</li>
        <li>{t.ui('members.offer.sources')}</li>
      </ul>
      <MembersActions />
      <p className="members-foot muted">
        {t.ui('members.reloadBefore')} <a href="./">{t.ui('members.reload')}</a>
      </p>
    </section>
  );
}
