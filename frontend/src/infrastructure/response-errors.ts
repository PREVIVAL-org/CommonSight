/**
 * Error types for responses that do not arrive as expected (HTTP status or contract shape).
 */
export class HttpStatusError extends Error {
  constructor(
    readonly url: string,
    readonly status: number,
  ) {
    super(`HTTP ${status} for ${url}`);
    this.name = 'HttpStatusError';
  }
}

export class ContractShapeError extends Error {
  constructor(readonly url: string) {
    super(`response does not match the contract: ${url}`);
    this.name = 'ContractShapeError';
  }
}

/** true if the error stems from an intentional abort (U-55). */
export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}
