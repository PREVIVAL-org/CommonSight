<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Auth;

use CommonSight\Model\Value\UtcInstant;

/** What a provider may look at: the cookies of the request, the address of the start page and the time. */
final readonly class AuthRequest
{
    /** @param array<string, string> $cookies */
    public function __construct(
        public array $cookies,
        /** the address of the start page, e.g. for a login that returns to it */
        public string $pageUrl,
        public UtcInstant $now,
    ) {}
}
