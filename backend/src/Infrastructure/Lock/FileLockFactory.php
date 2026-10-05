<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Lock;

use CommonSight\Infrastructure\Storage\StorageError;
use CommonSight\Port\Lock;
use CommonSight\Port\LockFactory;

/** Locks via flock(LOCK_EX | LOCK_NB) on locks/<name>.lock; effective between cron and PHP-FPM processes (F-04, Architecture 4.3). */
final class FileLockFactory implements LockFactory
{
    private const RETRY_MICROSECONDS = 50_000;

    public function __construct(private readonly string $locksDir) {}

    public function acquire(string $name): ?Lock
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid lock name: ' . $name);
        }
        if (!is_dir($this->locksDir) && !mkdir($this->locksDir, 0755, true) && !is_dir($this->locksDir)) {
            throw new StorageError('Cannot create lock directory: ' . $this->locksDir);
        }
        $handle = fopen($this->locksDir . '/' . $name . '.lock', 'c');
        if ($handle === false) {
            throw new StorageError('Cannot create lock file: ' . $name);
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return new FileLock($handle);
    }

    public function acquireWaiting(string $name, float $maxWaitSec): ?Lock
    {
        $until = microtime(true) + $maxWaitSec;
        while (($lock = $this->acquire($name)) === null && microtime(true) < $until) {
            usleep(self::RETRY_MICROSECONDS);
        }

        return $lock;
    }
}
