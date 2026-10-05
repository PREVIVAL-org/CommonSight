<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\Lock;
use CommonSight\Port\LockFactory;

/** Locks in memory; taken names can be preset. */
final class FakeLockFactory implements LockFactory
{
    /** @var array<string, true> */
    public array $held = [];

    public function acquire(string $name): ?Lock
    {
        if (isset($this->held[$name])) {
            return null;
        }
        $this->held[$name] = true;

        return new FakeLock(function () use ($name): void {
            unset($this->held[$name]);
        });
    }

    /** Nothing releases a lock in memory while waiting: taken stays taken. */
    public function acquireWaiting(string $name, float $maxWaitSec): ?Lock
    {
        return $this->acquire($name);
    }
}
