<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Value\UtcInstant;

/** Remembers per layer the last reload attempt of the fallback, locked against concurrent processes (Architecture 6.3). */
interface TriggerStamp
{
    /**
     * Sets the timestamp to $now if the last attempt was at least $minIntervalSec ago.
     *
     * @return bool true if this process may reload
     */
    public function claim(string $name, UtcInstant $now, int $minIntervalSec): bool;
}
