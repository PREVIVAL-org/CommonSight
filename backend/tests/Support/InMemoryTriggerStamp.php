<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\TriggerStamp;

/** Minimum interval of the fallback in memory. */
final class InMemoryTriggerStamp implements TriggerStamp
{
    /** @var array<string, int> */
    public array $stamps = [];

    public function claim(string $name, UtcInstant $now, int $minIntervalSec): bool
    {
        if (isset($this->stamps[$name]) && $now->timestamp - $this->stamps[$name] < $minIntervalSec) {
            return false;
        }
        $this->stamps[$name] = $now->timestamp;

        return true;
    }
}
