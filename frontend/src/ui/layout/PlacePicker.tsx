/**
 * Combined selection of country and state or canton in one field, first "Alle Länder" (all countries), then per
 * country "Ganz {Land}" (all of {country}) with its flag and below it its regions indented (U-10 to U-12, ADR 0035,
 * 0037). An own list (Radix Select) instead of a native select, because only that can show SVG flags in the list:
 * emoji flags are missing on some devices, and phones grey out group headings. Keyboard, type-ahead and screen
 * reader come from Radix. No visible label; the name is set as aria-label on the field (T-09).
 */
import * as Select from '@radix-ui/react-select';
import { Check, ChevronDown, ChevronUp } from 'lucide-react';
import type { MouseEvent, PointerEvent } from 'react';
import { useRef, useState } from 'react';
import { useActions, useAppState, usePortalContainer, useTexts } from '../hooks';
import { CountryFlag } from '../parts/CountryFlag';

function Options() {
  const options = useAppState((state, selectors) => selectors.placeOptions(state));
  return options.map((option) => (
    <Select.Item
      key={option.value}
      value={option.value}
      className="place-picker-item"
      data-region={option.region}
    >
      {option.country === null || option.region ? null : <CountryFlag country={option.country} />}
      <Select.ItemText>{option.name}</Select.ItemText>
      <Select.ItemIndicator className="place-picker-check">
        <Check size={16} aria-hidden="true" />
      </Select.ItemIndicator>
    </Select.Item>
  ));
}

/**
 * A second tap on the field closes the open list: on phones the list covers most of the screen, so there is little
 * "outside" left to tap. Radix only opens on the field and, while the list is open, lets the page ignore pointers; the
 * open field takes them again (layout.css).
 */
function useCloseOnSecondTap(open: boolean, onClose: () => void) {
  // The click that follows the closing tap: Radix opens on it for touch, so it is swallowed.
  const closing = useRef(false);
  return {
    onPointerDown: (event: PointerEvent) => {
      if (!open) return;
      event.preventDefault();
      closing.current = true;
      onClose();
    },
    onClick: (event: MouseEvent) => {
      if (!closing.current) return;
      event.preventDefault();
      closing.current = false;
    },
  };
}

/** The field: flag and name of the chosen place. */
function PlaceTrigger({ open, onClose }: { open: boolean; onClose: () => void }) {
  const t = useTexts();
  const display = useAppState((state, selectors) => selectors.placeDisplay(state));
  return (
    <Select.Trigger
      className="select place-picker"
      aria-label={t.ui('place.label')}
      {...useCloseOnSecondTap(open, onClose)}
    >
      <Select.Value>
        <span className="place-picker-label">
          {display.country === null ? null : <CountryFlag country={display.country} />}
          <span className="place-picker-text">{display.label}</span>
        </span>
      </Select.Value>
      <Select.Icon className="place-picker-arrow">
        <ChevronDown size={18} aria-hidden="true" />
      </Select.Icon>
    </Select.Trigger>
  );
}

export function PlacePicker() {
  const actions = useActions();
  const container = usePortalContainer();
  const value = useAppState((state, selectors) => selectors.placeValue(state));
  const [open, setOpen] = useState(false);
  return (
    <Select.Root
      value={value}
      open={open}
      onOpenChange={setOpen}
      onValueChange={(next) => actions.choosePlace(next)}
    >
      <PlaceTrigger open={open} onClose={() => setOpen(false)} />
      <Select.Portal container={container ?? undefined}>
        <Select.Content className="place-picker-content" position="popper" sideOffset={4}>
          <Select.ScrollUpButton className="place-picker-scroll">
            <ChevronUp size={16} aria-hidden="true" />
          </Select.ScrollUpButton>
          <Select.Viewport className="place-picker-viewport">
            <Options />
          </Select.Viewport>
          <Select.ScrollDownButton className="place-picker-scroll">
            <ChevronDown size={16} aria-hidden="true" />
          </Select.ScrollDownButton>
        </Select.Content>
      </Select.Portal>
    </Select.Root>
  );
}
