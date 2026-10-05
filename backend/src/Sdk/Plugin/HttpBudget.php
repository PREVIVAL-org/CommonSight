<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/**
 * HTTP limits of one source: time per request and size per response (F-08); the core enforces them. A limit the source
 * does not declare (null) is the one of the configuration (`http` in config.php); parallel requests are limited per host
 * by the configuration (http.perHostConcurrency, F-11).
 */
final readonly class HttpBudget
{
    public function __construct(
        public ?int $timeoutSec = null,
        public ?int $maxBytes = null,
    ) {
        if (($timeoutSec ?? 1) < 1 || ($maxBytes ?? 1024) < 1024) {
            throw new \InvalidArgumentException('HTTP limits must be positive');
        }
    }
}
