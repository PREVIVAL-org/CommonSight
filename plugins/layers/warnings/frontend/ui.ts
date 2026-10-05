/**
 * User interface of the layer warnings: the map notice summarizes the warning situation (U-23), a short notice
 * reports new warnings of an update, the detail sheet lists the further official information (e.g. the national
 * warning apps).
 */
import type { LayerUiPart, NoticeInput, NoticeValue } from '@sdk/ui';

function place({ regionName, format }: NoticeInput): string {
  return regionName ?? format.text({ key: 'layer.warnings.notice.placeSources' });
}

function readyNotice(input: NoticeInput): NoticeValue {
  const { count, unassigned, availability, format } = input;
  const incomplete = availability === 'partial';
  const parts = [
    count === 0
      ? format.text({ key: 'layer.warnings.notice.none', params: { place: place(input) } })
      : format.text({ key: 'layer.warnings.notice.count', params: { count, place: place(input) } }),
  ];
  if (incomplete) parts.push(format.text({ key: 'layer.warnings.notice.incomplete' }));
  if (unassigned > 0) parts.push(format.text({ key: 'layer.warnings.notice.unassigned' }));
  return { tone: count > 0 || incomplete ? 'warn' : 'info', text: parts.join(' · '), hasEntries: count > 0 };
}

function summary(input: NoticeInput): NoticeValue {
  const { availability, format } = input;
  if (availability === 'loading' || availability === 'pending') {
    return { tone: 'info', text: format.text({ key: 'layer.warnings.notice.loading' }), hasEntries: false };
  }
  if (availability === 'error' || availability === 'setup') {
    return { tone: 'error', text: format.text({ key: 'layer.warnings.notice.error' }), hasEntries: false };
  }
  return readyNotice(input);
}

export const ui: LayerUiPart = {
  officialLinks: true,
  notice: {
    priority: 10,
    open: { key: 'layer.warnings.notice.open' },
    browse: { key: 'layer.warnings.browse' },
    summary,
  },
  newEntriesToast: 'layer.warnings.toast.new',
};
