<?php

declare(strict_types=1);

namespace CommonSight\Model\Http;

/** Successful response (status 2xx) to a request. */
final readonly class HttpResponse
{
    public function __construct(
        public HttpRequest $request,
        public int $status,
        public string $body,
        public ResponseHeaders $headers = new ResponseHeaders(),
    ) {}
}
