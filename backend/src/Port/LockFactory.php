<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Acquires named locks (F-04): sources without waiting, layers waiting briefly for an assembly in progress. */
interface LockFactory
{
    /** @return Lock|null null if the lock is taken */
    public function acquire(string $name): ?Lock;

    /** @return Lock|null null if the lock is still taken after $maxWaitSec */
    public function acquireWaiting(string $name, float $maxWaitSec): ?Lock;
}
