/**
 * Decides whether light or dark applies: host page setting, else own choice, else system setting (T-05).
 */
export type ThemeMode = 'light' | 'dark';

export interface ThemeInputs {
  attribute: ThemeMode | null;
  choice: ThemeMode | null;
  systemDark: boolean;
}

export function resolveThemeMode(inputs: ThemeInputs): ThemeMode {
  if (inputs.attribute !== null) return inputs.attribute;
  if (inputs.choice !== null) return inputs.choice;
  return inputs.systemDark ? 'dark' : 'light';
}

/** Reads an attribute value as a theme; anything else counts as "no preset". */
export function parseThemeMode(value: unknown): ThemeMode | null {
  return value === 'light' || value === 'dark' ? value : null;
}
