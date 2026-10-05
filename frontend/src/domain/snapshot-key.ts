/**
 * Builds the key under which a snapshot is stored per scope and layer.
 */
import { layerMeta } from '../contract/master-data';
import type { Country, LayerId, Scope } from '../contract/types';
import type { CountryChoice } from './country-choice';
import { countriesOf } from './country-choice';

export type SnapshotKey = `${Scope}/${LayerId}`;

/** Scope of a layer in the chosen country: country-independent layers count as `global`. */
export function scopeOf(layer: LayerId, country: Country): Scope {
  return layerMeta(layer).scope === 'global' ? 'global' : country;
}

export function snapshotKey(layer: LayerId, country: Country): SnapshotKey {
  return `${scopeOf(layer, country)}/${layer}`;
}

/** Key of the snapshot of a layer in the border zone beyond DACH. */
export function borderKey(layer: LayerId): SnapshotKey {
  return `border/${layer}`;
}

/** Keys of all snapshots of a layer in the choice; `global` only once. */
export function snapshotKeysOf(layer: LayerId, choice: CountryChoice): SnapshotKey[] {
  return [...new Set(countriesOf(choice).map((country) => snapshotKey(layer, country)))];
}
