<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\Clock;

/** Clock with a fixed, adjustable time. */
final class FakeClock implements Clock
{
    public function __construct(public UtcInstant $now) {}

    public function now(): UtcInstant
    {
        return $this->now;
    }
}
