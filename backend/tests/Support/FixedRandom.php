<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\RandomSource;

/** Randomness with a fixed value. */
final class FixedRandom implements RandomSource
{
    public function __construct(private readonly float $value = 0.0) {}

    public function fraction(): float
    {
        return $this->value;
    }
}
