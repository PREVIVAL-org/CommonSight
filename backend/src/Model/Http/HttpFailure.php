<?php

declare(strict_types=1);

namespace CommonSight\Model\Http;

/** Failed request with kind and description of the error; with the waiting time the server asked for (429, 503), if any. */
final readonly class HttpFailure
{
    public function __construct(
        public HttpRequest $request,
        public FailureKind $kind,
        public string $reason,
        public ?int $retryAfterSec = null,
    ) {}
}
