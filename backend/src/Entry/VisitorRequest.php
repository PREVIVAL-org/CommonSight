<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Auth\AuthRequest;

/** The request of a visitor as the auth provider sees it (A-D5): only the text cookies, the page and the time. */
final class VisitorRequest
{
    /** @param array<mixed> $cookies */
    public static function of(array $cookies, string $pageUrl): AuthRequest
    {
        return new AuthRequest(
            array_filter($cookies, static fn(mixed $v, mixed $k): bool => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH),
            $pageUrl,
            UtcInstant::fromTimestamp(time()),
        );
    }
}
