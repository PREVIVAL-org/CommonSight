/**
 * Merges status and snapshots of a layer from several countries, for the choice "Alle" (ADR 0037); pure.
 * Country-independent layers (`global`) appear identically in every country status and are not multiplied.
 */
import type { Item, LayerStatus, Msg, Snapshot } from '../contract/types';

/** Equal values stay, different ones yield the mixed value (partially available). */
function combined<T extends string>(values: readonly T[], mixed: T): T {
  const [first] = values;
  return values.every((value) => value === first) && first !== undefined ? first : mixed;
}

function present(values: readonly (string | null)[]): string[] {
  return values.filter((value): value is string => value !== null).sort();
}

/** Oldest point in time (ISO 8601 in UTC, lexically comparable); conservative for age and freshness. */
function oldest(values: readonly (string | null)[]): string | null {
  return present(values)[0] ?? null;
}

function newest(values: readonly (string | null)[]): string | null {
  return present(values).at(-1) ?? null;
}

function uniqueMessages(messages: readonly Msg[]): Msg[] {
  const seen = new Set<string>();
  return messages.filter((message) => {
    const key = JSON.stringify(message);
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

/** An item that appears in several countries (e.g. an earthquake in a border area) counts once. */
function uniqueItems(items: readonly Item[]): Item[] {
  const seen = new Set<string>();
  return items.filter((item) => {
    if (seen.has(item.id)) return false;
    seen.add(item.id);
    return true;
  });
}

function joinedVersion(entries: readonly LayerStatus[]): string | null {
  const versions = entries.map((entry) => entry.version);
  return versions.every((version) => version === null) ? null : versions.map((v) => v ?? '-').join('+');
}

export function mergeLayerStatus(entries: readonly LayerStatus[]): LayerStatus | undefined {
  const [first] = entries;
  if (first === undefined || entries.length === 1 || first.scope === 'global') return first;
  return {
    ...first,
    status: combined(
      entries.map((entry) => entry.status),
      'partial',
    ),
    version: joinedVersion(entries),
    // A merged layer has no file of its own; loading happens per country.
    url: null,
    checkedAt: oldest(entries.map((entry) => entry.checkedAt)),
    generatedAt: oldest(entries.map((entry) => entry.generatedAt)),
    sourceUpdatedAt: newest(entries.map((entry) => entry.sourceUpdatedAt)),
    itemCount: entries.reduce((sum, entry) => sum + entry.itemCount, 0),
    issues: uniqueMessages(entries.flatMap((entry) => entry.issues)),
    stale: entries.some((entry) => entry.stale),
    intervalSec: Math.min(...entries.map((entry) => entry.intervalSec)),
    lastError: entries.find((entry) => entry.lastError !== null)?.lastError ?? null,
  };
}

/** Items in the order of the countries; statistics (`stats`) and notice from the first country. */
export function mergeSnapshots(snapshots: readonly Snapshot[]): Snapshot | undefined {
  const [first] = snapshots;
  if (first === undefined || snapshots.length === 1 || first.scope === 'global') return first;
  return {
    ...first,
    status: combined(
      snapshots.map((snapshot) => snapshot.status),
      'partial',
    ),
    source: [...new Set(snapshots.map((snapshot) => snapshot.source))].join(' · '),
    generatedAt: oldest(snapshots.map((snapshot) => snapshot.generatedAt)) ?? first.generatedAt,
    updatedAt: newest(snapshots.map((snapshot) => snapshot.updatedAt)),
    issues: uniqueMessages(snapshots.flatMap((snapshot) => snapshot.issues)),
    items: uniqueItems(snapshots.flatMap((snapshot) => snapshot.items)),
  };
}
