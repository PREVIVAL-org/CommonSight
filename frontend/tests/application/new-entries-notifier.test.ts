/**
 * Short notice about new entries after an update (today "neue amtliche Warnungen" of the warnings): only with new
 * entries and only in the wide layout.
 */
import { describe, expect, it } from 'vitest';
import { startNewEntriesNotifier } from '../../src/application/new-entries-notifier';
import type { Snapshot } from '../../src/contract/types';
import { snapshot, statusAT } from '../support/fixtures';
import { createTestStore, loadInto } from '../support/store';

const REF = { key: 'AT/warnings', layer: 'warnings', scope: 'AT', url: 'data/v1/AT/warnings.json' } as const;

function withNewWarnings(count: number): Snapshot {
  const base = snapshot('warnings-AT');
  const added = Array.from({ length: count }, (_, i) => ({ ...base.items[0], id: `neu-${i}` }));
  return { ...base, items: [...base.items, ...added] } as Snapshot;
}

function setup(narrow: boolean) {
  const bundle = createTestStore('AT');
  loadInto(bundle, statusAT(), [snapshot('warnings-AT')]);
  startNewEntriesNotifier(bundle.store, bundle.actions, { isNarrow: () => narrow });
  return bundle;
}

describe('new entries notifier', () => {
  it('shows a notice with the number of new warnings', () => {
    const bundle = setup(false);
    bundle.actions.snapshotLoaded({ ...REF, version: 'test0002' }, withNewWarnings(2));
    expect(bundle.store.getState().ui.toasts).toEqual([
      { id: 1, tone: 'info', message: { key: 'layer.warnings.toast.new', params: { count: 2 } } },
    ]);
  });

  it('stays silent without new warnings and on narrow screens', () => {
    const quiet = setup(false);
    quiet.actions.snapshotLoaded({ ...REF, version: 'test0002' }, snapshot('warnings-AT'));
    expect(quiet.store.getState().ui.toasts).toEqual([]);
    const narrow = setup(true);
    narrow.actions.snapshotLoaded({ ...REF, version: 'test0002' }, withNewWarnings(1));
    expect(narrow.store.getState().ui.toasts).toEqual([]);
  });

  it('counts nothing that came while another country was selected', () => {
    const bundle = setup(false);
    bundle.actions.setCountry('DE');
    // The Austrian snapshot is updated in the background, e.g. for the border zone.
    bundle.actions.snapshotLoaded({ ...REF, version: 'test0002' }, withNewWarnings(3));
    bundle.actions.setCountry('AT');
    expect(bundle.store.getState().ui.toasts).toEqual([]);
    bundle.actions.snapshotLoaded({ ...REF, version: 'test0003' }, withNewWarnings(4));
    expect(bundle.store.getState().ui.toasts).toEqual([
      { id: 1, tone: 'info', message: { key: 'layer.warnings.toast.new', params: { count: 1 } } },
    ]);
  });
});
