<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\OutcomeStore;

/** Latest outcomes of the sources in memory. */
final class InMemoryOutcomes implements OutcomeStore
{
    /** @var array<string, string> */
    public array $outcomes = [];

    public function read(string $key): ?string
    {
        return $this->outcomes[$key] ?? null;
    }

    public function write(string $key, string $outcome): void
    {
        $this->outcomes[$key] = $outcome;
    }
}
