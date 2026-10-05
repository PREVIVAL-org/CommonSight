/**
 * The side panel with the news items of the layer that contributes it (today news): topic filter (select field in the
 * header), compact news cards, empty, error and loading state (U-26).
 */
import type { ChangeEvent } from 'react';
import { catalog, NEWS_CATEGORIES, termLabel } from '../../contract/catalog';
import type { NewsTopic } from '../../domain/selection';
import type { PanelModel } from '../../domain/views/panel-view';
import { ItemCard } from '../cards/ItemCard';
import { useActions, useAppState, useTexts } from '../hooks';
import { ExternalLink } from '../parts/ExternalLink';

/** Topic filter as a select field in the news header; topics from the catalog; aria-label on the field (T-09). */
function TopicFilter() {
  const t = useTexts();
  const actions = useActions();
  const topic = useAppState((state) => state.selection.newsTopic);
  const change = (event: ChangeEvent<HTMLSelectElement>): void =>
    actions.setNewsTopic(event.target.value as NewsTopic);
  return (
    <select className="select topic-filter" aria-label={t.ui('news.topics')} value={topic} onChange={change}>
      <option value="all">{t.ui('news.topic.all')}</option>
      {NEWS_CATEGORIES.map((id) => (
        <option key={id} value={id}>
          {termLabel(catalog.newsCategories, id)}
        </option>
      ))}
    </select>
  );
}

function NewsBody({ news }: { news: PanelModel }) {
  const t = useTexts();
  if (news.availability === 'loading' || news.availability === 'pending') {
    return (
      <div className="news-list" aria-busy="true" aria-label={t.ui('news.loading')}>
        <div className="placeholder" />
        <div className="placeholder" />
        <div className="placeholder" />
      </div>
    );
  }
  if (news.views.length === 0 && news.availability === 'error') {
    return (
      <div className="panel-body">
        <p>{t.ui('news.error')}</p>
        <ExternalLink href={news.fallbackUrl}>{t.ui('news.openSource')}</ExternalLink>
      </div>
    );
  }
  if (news.views.length === 0) return <p className="panel-body muted">{t.ui('news.empty')}</p>;
  return (
    <ul className="news-list">
      {news.views.map((view) => (
        <li key={view.base.id}>
          <ItemCard view={view} variant="compact" />
        </li>
      ))}
    </ul>
  );
}

export function NewsPanel() {
  const t = useTexts();
  const news = useAppState((state, selectors) => selectors.panel(state));
  if (news === null) return null;
  return (
    <section className="panel overview-news" part="news" aria-labelledby="cs-news-title">
      <div className="panel-head">
        <h2 className="panel-title" id="cs-news-title">
          {t.msg(news.title)}
        </h2>
        <TopicFilter />
      </div>
      <NewsBody news={news} />
      <div className="panel-foot">
        {t.ui('news.footer', { count: news.intervalMinutes })}
        {news.availability === 'partial' ? ` · ${t.ui('news.partial')}` : null}
      </div>
    </section>
  );
}
