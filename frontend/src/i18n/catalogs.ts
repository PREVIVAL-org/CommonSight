/**
 * Selects the catalogs for the element's language; so far only German exists (I-01).
 */
import type { MessageText } from '../contract/messages';
import { contractMessagesDe } from '../contract/messages';
import type { UiText, UiTextKey } from './ui-texts.de';
import { uiTextsDe } from './ui-texts.de';

export interface Catalogs {
  lang: string;
  uiTexts: Readonly<Record<UiTextKey, UiText>>;
  contractMessages: Readonly<Record<string, MessageText>>;
}

export function catalogsFor(_lang: string): Catalogs {
  return { lang: 'de', uiTexts: uiTextsDe, contractMessages: contractMessagesDe };
}
