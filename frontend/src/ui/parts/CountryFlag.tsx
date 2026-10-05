/**
 * Flags of the three countries as inline SVG instead of emoji: emoji flags are missing on some phones and on
 * Windows (shown as "DE", "AT", "CH"). Fixed colors of the national flags, independent of the theme; a thin
 * outline keeps white parts visible on light backgrounds.
 */
import type { Country } from '../../contract/types';

const OUTLINE = 'rgb(0 0 0 / 0.25)';

function Germany() {
  return (
    <svg viewBox="0 0 5 3" width="21" height="13" aria-hidden="true" className="country-flag">
      <rect width="5" height="1" fill="#000000" />
      <rect y="1" width="5" height="1" fill="#dd0000" />
      <rect y="2" width="5" height="1" fill="#ffce00" />
      <rect width="5" height="3" fill="none" stroke={OUTLINE} strokeWidth="0.1" />
    </svg>
  );
}

function Austria() {
  return (
    <svg viewBox="0 0 5 3" width="21" height="13" aria-hidden="true" className="country-flag">
      <rect width="5" height="3" fill="#c8102e" />
      <rect y="1" width="5" height="1" fill="#ffffff" />
      <rect width="5" height="3" fill="none" stroke={OUTLINE} strokeWidth="0.1" />
    </svg>
  );
}

function Switzerland() {
  return (
    <svg viewBox="0 0 32 32" width="15" height="15" aria-hidden="true" className="country-flag">
      <rect width="32" height="32" fill="#da291c" />
      <rect x="13" y="6" width="6" height="20" fill="#ffffff" />
      <rect x="6" y="13" width="20" height="6" fill="#ffffff" />
    </svg>
  );
}

export function CountryFlag({ country }: { country: Country }) {
  switch (country) {
    case 'DE':
      return <Germany />;
    case 'AT':
      return <Austria />;
    case 'CH':
      return <Switzerland />;
  }
}
