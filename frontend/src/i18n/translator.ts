/**
 * Translates UI keys and backend Msg into text in the selected language (E12, V10).
 */
import type { MessageText } from '../contract/messages';
import type { LayerId, Msg } from '../contract/types';
import type { UiText, UiTextKey } from './ui-texts.de';

export type TextParams = Readonly<Record<string, string | number | undefined>>;

export interface Translator {
  ui(key: UiTextKey, params?: TextParams): string;
  msg(message: Msg): string;
  /** true if a text exists for this Msg key. */
  knows(key: string): boolean;
  /** Name of a layer from the texts of its package (layer.<id>.name); the id itself without one. */
  layer(id: LayerId): string;
}

export interface TranslatorSources {
  uiTexts: Readonly<Record<UiTextKey, UiText>>;
  contractMessages: Readonly<Record<string, MessageText>>;
  /** language of the texts; decides the plural forms */
  locale: string;
  formatNumber: (value: number) => string;
}

const PLACEHOLDER = /\{(\w+)\}/g;

function interpolate(template: string, params: TextParams, formatNumber: (value: number) => string): string {
  return template.replace(PLACEHOLDER, (whole, name: string) => {
    const value = params[name];
    if (value === undefined) return whole;
    return typeof value === 'number' ? formatNumber(value) : value;
  });
}

function pickPlural(text: UiText | MessageText, params: TextParams, rules: Intl.PluralRules): string {
  if (typeof text === 'string') return text;
  const count = params.count;
  return typeof count === 'number' && rules.select(count) === 'one' ? text.one : text.other;
}

export function createTranslator(sources: TranslatorSources): Translator {
  const rules = new Intl.PluralRules(sources.locale);
  return {
    ui: (key, params = {}) =>
      interpolate(pickPlural(sources.uiTexts[key], params, rules), params, sources.formatNumber),
    msg: (message) => {
      const text = sources.contractMessages[message.key];
      const params = message.params ?? {};
      return text === undefined
        ? message.key
        : interpolate(pickPlural(text, params, rules), params, sources.formatNumber);
    },
    knows: (key) => sources.contractMessages[key] !== undefined,
    layer: (id) => {
      const name = sources.contractMessages[`layer.${id}.name`];
      return typeof name === 'string' ? name : id;
    },
  };
}
