<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Value\UtcInstant;

/** Returns the current time. */
interface Clock
{
    public function now(): UtcInstant;
}
