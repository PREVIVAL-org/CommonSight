<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Recording;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\ResponseHeaders;

/** A recorded request with its response; the request is identified without its secrets (redacted URL, body hash). */
final readonly class RecordedExchange
{
    public function __construct(
        public string $signature,
        public int $status,
        public ResponseHeaders $headers,
        public string $body,
    ) {}

    /** Identifies a request independently of secret values: method, redacted URL and hash of the body. */
    public static function signatureOf(HttpRequest $request): string
    {
        $body = $request->body === null ? '' : ' ' . sha1($request->body->content);

        return ($request->body === null ? 'GET ' : 'POST ') . $request->redactedUrl() . $body;
    }
}
