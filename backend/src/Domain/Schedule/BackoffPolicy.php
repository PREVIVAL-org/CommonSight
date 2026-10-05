<?php

declare(strict_types=1);

namespace CommonSight\Domain\Schedule;

use CommonSight\Model\Value\UtcInstant;

/**
 * Determines the backoff time of a source after failures: 1 min, doubled up to at most 30 min, with a random component;
 * a Retry-After of the provider is honoured, up to a day (F-05, Architecture 4.7; concept: sources as plugins, 6).
 */
final class BackoffPolicy
{
    private const FIRST_SEC = 60;
    private const MAX_SEC = 1800;
    private const MAX_RETRY_AFTER_SEC = 86_400;
    private const JITTER_SHARE = 0.2;

    /** @param float $randomFraction random value in [0, 1) */
    public function until(int $consecutiveFailures, UtcInstant $now, float $randomFraction, ?int $retryAfterSec = null): UtcInstant
    {
        $exponent = max(0, $consecutiveFailures - 1);
        $base = min(self::FIRST_SEC * 2 ** min($exponent, 10), self::MAX_SEC);
        $jitter = (int) round($base * self::JITTER_SHARE * $randomFraction);
        $seconds = min($base + $jitter, self::MAX_SEC);

        return $now->plusSeconds(max($seconds, min($retryAfterSec ?? 0, self::MAX_RETRY_AFTER_SEC)));
    }
}
