<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\Scope;
use CommonSight\Port\SourceHealthStore;

/** Health of the sources in memory, by key (e.g. "open-meteo-weather-AT"). */
final class InMemoryHealth implements SourceHealthStore
{
    /** @var array<string, SourceHealth> */
    public array $health = [];

    public function read(string $sourceId, Scope $scope): ?SourceHealth
    {
        return $this->health[$sourceId . '-' . $scope->value] ?? null;
    }

    public function write(SourceHealth $health): void
    {
        $this->health[$health->key()] = $health;
    }
}
