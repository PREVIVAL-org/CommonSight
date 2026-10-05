<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Place\BoundingBox;
use CommonSight\Model\Place\Station;

/** Stations of one source by ID, for joining current values with name, position and flood stages. */
final class StationDirectory
{
    /** @var array<string, Station> */
    private array $byId = [];

    /** @param list<Station> $stations */
    public function __construct(array $stations)
    {
        foreach ($stations as $station) {
            $this->byId[$station->id] = $station;
        }
    }

    public function find(string $id): ?Station
    {
        return $this->byId[$id] ?? null;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->byId);
    }

    /** Smallest rectangle around all stations, for sources that are queried by area. */
    public function bounds(): BoundingBox
    {
        if ($this->byId === []) {
            throw new \LogicException('No stations');
        }
        $lats = array_map(static fn(Station $s): float => $s->position->lat, $this->byId);
        $lons = array_map(static fn(Station $s): float => $s->position->lon, $this->byId);

        return new BoundingBox(min($lons), min($lats), max($lons), max($lats));
    }
}
