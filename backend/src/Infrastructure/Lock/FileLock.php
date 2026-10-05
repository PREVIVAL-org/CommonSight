<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Lock;

use CommonSight\Port\Lock;

/** A held flock lock; if the process dies, the operating system releases it. */
final class FileLock implements Lock
{
    /** @param resource $handle */
    public function __construct(private $handle) {}

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}
