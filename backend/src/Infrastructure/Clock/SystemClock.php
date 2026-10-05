<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Clock;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\Clock;

/** Returns the system time as a UTC instant, independent of PHP's default time zone. */
final class SystemClock implements Clock
{
    public function now(): UtcInstant
    {
        return UtcInstant::fromTimestamp(time());
    }
}
