/**
 * Reads and writes settings in localStorage; missing or blocked storage has no consequences (U-60, U-61).
 */

export interface SettingsStorage {
  readSettings(): unknown;
  writeSettings(value: unknown): void;
  readTheme(): unknown;
  writeTheme(value: string): void;
}

/** Access to the browser's storage; `null` if it is not reachable. */
export function browserStorage(win: Window): Storage | null {
  try {
    return win.localStorage;
  } catch {
    // Blocked storage (e.g. privacy settings) means settings are not saved.
    return null;
  }
}

function readJson(storage: Storage | null, key: string): unknown {
  try {
    const raw = storage?.getItem(key) ?? null;
    return raw === null ? null : (JSON.parse(raw) as unknown);
  } catch {
    // Unreadable or blocked values count as not stored (U-60).
    return null;
  }
}

function writeJson(storage: Storage | null, key: string, value: unknown): void {
  try {
    storage?.setItem(key, JSON.stringify(value));
  } catch {
    // Full or blocked storage: the setting only lasts until the next reload (U-60).
  }
}

export function createSettingsStorage(storage: Storage | null, key: string): SettingsStorage {
  return {
    readSettings: () => readJson(storage, key),
    writeSettings: (value) => writeJson(storage, key, value),
    readTheme: () => readJson(storage, `${key}:theme`),
    writeTheme: (value) => writeJson(storage, `${key}:theme`, value),
  };
}
