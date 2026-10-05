/**
 * Loads static JSON files of the map assets (tiles manifest, region outline) relative to the base URL.
 */
import { HttpStatusError } from './response-errors';
import type { FetchFn } from './status-api';

export interface JsonFileApi {
  fetchJson(path: string, signal?: AbortSignal): Promise<unknown>;
  /** Absolute URL of a file under the base URL. */
  urlOf(path: string): string;
}

export class HttpJsonFileApi implements JsonFileApi {
  constructor(
    private readonly baseUrl: string,
    private readonly fetchFn: FetchFn,
  ) {}

  urlOf(path: string): string {
    return `${this.baseUrl}${path}`;
  }

  async fetchJson(path: string, signal?: AbortSignal): Promise<unknown> {
    const url = this.urlOf(path);
    const response = await this.fetchFn(url, signal === undefined ? {} : { signal });
    if (!response.ok) throw new HttpStatusError(url, response.status);
    return (await response.json()) as unknown;
  }
}
