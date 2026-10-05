<?php

declare(strict_types=1);

namespace CommonSight\Port;

/**
 * Small key-value store of one plugin for what must survive a run: validators of conditional requests, tokens, cursors,
 * detail caches. Each plugin sees only its own entries.
 */
interface PluginStore
{
    /** The stored value, or null if there is none or it has expired. */
    public function read(string $key): ?string;

    /** Stores the value; with a lifetime it expires after that many seconds. */
    public function write(string $key, string $value, ?int $ttlSec = null): void;

    public function delete(string $key): void;
}
