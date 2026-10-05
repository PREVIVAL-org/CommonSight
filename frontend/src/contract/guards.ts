/**
 * Checks on receipt whether a response has the basic shape of the contract (schema version 1).
 */
import { LAYER_IDS } from './master-data';
import type { Snapshot, StatusResponse } from './types';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Response of the status endpoint in schema version 1 with a layers object. */
export function isStatusResponse(value: unknown): value is StatusResponse {
  return (
    isRecord(value) &&
    value.schema === 1 &&
    typeof value.serverTime === 'string' &&
    typeof value.scope === 'string' &&
    isRecord(value.layers)
  );
}

/** Snapshot in schema version 1 with a known layer and a list of items. */
export function isSnapshot(value: unknown): value is Snapshot {
  return (
    isRecord(value) &&
    value.schema === 1 &&
    typeof value.layer === 'string' &&
    (LAYER_IDS as readonly string[]).includes(value.layer) &&
    Array.isArray(value.items) &&
    isRecord(value.note) &&
    Array.isArray(value.issues)
  );
}
