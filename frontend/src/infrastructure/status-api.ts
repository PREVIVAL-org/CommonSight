/**
 * Fetches the status endpoint, with ETag/If-None-Match and abort (U-52, U-55, Architecture 6.2).
 */
import { isStatusResponse } from '../contract/guards';
import type { Country, StatusResponse } from '../contract/types';
import { ContractShapeError, HttpStatusError } from './response-errors';

export type FetchFn = (input: string, init?: RequestInit) => Promise<Response>;

export interface StatusApi {
  /** On `304` returns the same response (the same object) as on the last `200`. */
  fetchStatus(country: Country, signal: AbortSignal): Promise<StatusResponse>;
}

interface CachedStatus {
  etag: string;
  body: StatusResponse;
}

export class HttpStatusApi implements StatusApi {
  private readonly cache = new Map<Country, CachedStatus>();

  constructor(
    private readonly baseUrl: string,
    private readonly fetchFn: FetchFn,
  ) {}

  async fetchStatus(country: Country, signal: AbortSignal): Promise<StatusResponse> {
    const url = `${this.baseUrl}api/status.php?scope=${country}`;
    const cached = this.cache.get(country);
    const headers: Record<string, string> = cached === undefined ? {} : { 'If-None-Match': cached.etag };
    const response = await this.fetchFn(url, { signal, headers, credentials: 'same-origin' });
    if (response.status === 304 && cached !== undefined) return cached.body;
    if (!response.ok) throw new HttpStatusError(url, response.status);
    const body: unknown = await response.json();
    if (!isStatusResponse(body)) throw new ContractShapeError(url);
    const etag = response.headers.get('ETag');
    if (etag === null) this.cache.delete(country);
    else this.cache.set(country, { etag, body });
    return body;
  }
}
