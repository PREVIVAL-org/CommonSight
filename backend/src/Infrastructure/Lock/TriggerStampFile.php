<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Lock;

use CommonSight\Infrastructure\Storage\StorageError;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\TriggerStamp;

/** Keeps per layer in locks/<name> the time of the last reload attempt, locked with flock (Architecture 6.3). */
final class TriggerStampFile implements TriggerStamp
{
    public function __construct(private readonly string $locksDir) {}

    public function claim(string $name, UtcInstant $now, int $minIntervalSec): bool
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid name: ' . $name);
        }
        // A status request can come before the first cron run has created the folder (fresh installation).
        if (!is_dir($this->locksDir) && !mkdir($this->locksDir, 0755, true) && !is_dir($this->locksDir)) {
            throw new StorageError('Cannot create lock directory: ' . $this->locksDir);
        }
        $handle = fopen($this->locksDir . '/' . $name, 'c+');
        if ($handle === false) {
            throw new StorageError('Cannot create timestamp: ' . $name);
        }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return false;
            }
            $last = (int) stream_get_contents($handle);
            if ($now->timestamp - $last < $minIntervalSec) {
                return false;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $now->timestamp);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
