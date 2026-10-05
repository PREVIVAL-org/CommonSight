/**
 * Reports changes of country, region and layers to the outside as the event `commonsight-change`
 * (Architecture 9.6).
 */
import type { LayerId } from '../contract/types';
import type { CountryChoice } from '../domain/country-choice';
import type { AppStore } from '../state/store';

export interface ChangeDetail {
  /** `DE`, `AT`, `CH` or `ALL` (ADR 0037). */
  country: CountryChoice;
  regionId: string | null;
  layers: LayerId[];
}

export function startChangeNotifier(store: AppStore, emit: (detail: ChangeDetail) => void): () => void {
  return store.subscribe((state, previous) => {
    const a = state.selection;
    const b = previous.selection;
    if (a.country === b.country && a.regionId === b.regionId && a.activeLayers === b.activeLayers) return;
    emit({ country: a.country, regionId: a.regionId, layers: [...a.activeLayers] });
  });
}
