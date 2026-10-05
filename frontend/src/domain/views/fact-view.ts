/**
 * Prepares additional values (D-16) as label and formatted value.
 */
import type { Fact } from '../../contract/types';
import type { ViewDeps } from './view-deps';

export interface FactView {
  label: string;
  value: string;
}

export function toFactViews(facts: readonly Fact[], deps: ViewDeps): FactView[] {
  return facts.map((fact) => {
    const value = typeof fact.value === 'number' ? deps.format.number(fact.value, 2) : fact.value;
    return {
      label: deps.t.msg(fact.label),
      value: fact.unit === undefined ? value : `${value} ${fact.unit}`,
    };
  });
}
