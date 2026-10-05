/**
 * Fetches a snapshot from its versioned, immutable URL (Architecture 6.1).
 */
import { isSnapshot } from '../contract/guards';
import type { Snapshot } from '../contract/types';
import { ContractShapeError, HttpStatusError } from './response-errors';
import type { FetchFn } from './status-api';

export interface SnapshotApi {
  /** @param path relative to the base URL, as delivered in the status */
  fetchSnapshot(path: string, signal: AbortSignal): Promise<Snapshot>;
}

export class HttpSnapshotApi implements SnapshotApi {
  constructor(
    private readonly baseUrl: string,
    private readonly fetchFn: FetchFn,
  ) {}

  async fetchSnapshot(path: string, signal: AbortSignal): Promise<Snapshot> {
    const url = `${this.baseUrl}${path.replace(/^\//, '')}`;
    const response = await this.fetchFn(url, { signal, credentials: 'same-origin' });
    if (!response.ok) throw new HttpStatusError(url, response.status);
    const body: unknown = await response.json();
    if (!isSnapshot(body)) throw new ContractShapeError(url);
    return body;
  }
}
