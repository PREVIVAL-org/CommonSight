/**
 * Switch "Grenzgebiet" (border area) next to the country selection: shows the measuring points of the neighbouring
 * countries in the border zone on the map and in the lists, or only DACH (ADR 0038).
 */
import * as Switch from '@radix-ui/react-switch';
import { useId } from 'react';
import { useActions, useAppState, useTexts } from '../hooks';

export function BorderToggle() {
  const t = useTexts();
  const actions = useActions();
  const id = useId();
  const on = useAppState((state) => state.selection.borderZone);
  const km = useAppState((state, selectors) => selectors.vicinityKm(state));
  const hint = t.ui('border.hint', { km });
  return (
    <span className="border-toggle" title={hint}>
      <Switch.Root
        id={id}
        className="switch"
        checked={on}
        aria-labelledby={`${id}-label`}
        aria-describedby={`${id}-hint`}
        onCheckedChange={(checked) => actions.setBorderZone(checked)}
      >
        <Switch.Thumb className="switch-thumb" />
      </Switch.Root>
      {/* Named explicitly: across the shadow root not every assistive technology follows label/for. */}
      <label id={`${id}-label`} htmlFor={id}>
        {t.ui('border.toggle')}
      </label>
      <span id={`${id}-hint`} className="visually-hidden">
        {hint}
      </span>
    </span>
  );
}
