<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Clock;

use CommonSight\Port\RandomSource;

/** Returns random values from PHP's random number generator. */
final class SystemRandom implements RandomSource
{
    public function fraction(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }
}
