<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Returns random values, e.g. for the random component of the backoff. */
interface RandomSource
{
    /** Uniformly distributed in [0, 1). */
    public function fraction(): float;
}
