<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\Lock;

/** Held lock in memory. */
final class FakeLock implements Lock
{
    public function __construct(private readonly \Closure $onRelease) {}

    public function release(): void
    {
        ($this->onRelease)();
    }
}
