<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\RunMarker;

/** Run markers in memory; a marker left behind stands for a process that died. */
final class InMemoryRunMarker implements RunMarker
{
    /** @var array<string, string> */
    public array $running = [];
    /** @var array<string, UtcInstant> */
    public array $startedAt = [];

    public function start(string $process, string $sourceKey, UtcInstant $startedAt): void
    {
        $this->running[$process] = $sourceKey;
        $this->startedAt[$process] = $startedAt;
    }

    public function finish(string $process): void
    {
        unset($this->running[$process], $this->startedAt[$process]);
    }

    public function startedAt(string $process): ?UtcInstant
    {
        return $this->startedAt[$process] ?? null;
    }

    public function unfinished(string $process): ?string
    {
        return $this->running[$process] ?? null;
    }
}
