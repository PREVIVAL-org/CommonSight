<?php

declare(strict_types=1);

namespace CommonSight\Model\Http;

/** The response headers that the flow or a plugin may use: validators for conditional requests, waiting time, paging, type. */
final readonly class ResponseHeaders
{
    public function __construct(
        public ?string $etag = null,
        public ?string $lastModified = null,
        /** waiting time in seconds from Retry-After (seconds or HTTP date), not negative */
        public ?int $retryAfterSec = null,
        /** Link header (RFC 8288), e.g. for paging */
        public ?string $link = null,
        public ?string $contentType = null,
    ) {
        if ($retryAfterSec !== null && $retryAfterSec < 0) {
            throw new \InvalidArgumentException('Negative Retry-After');
        }
    }
}
