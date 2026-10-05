<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\PluginStore;

/** Store of a plugin in memory, fresh for every test chain; lifetimes are not needed in the tests. */
final class InMemoryPluginStore implements PluginStore
{
    /** @var array<string, string> */
    public array $values = [];

    public function read(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function write(string $key, string $value, ?int $ttlSec = null): void
    {
        $this->values[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }
}
