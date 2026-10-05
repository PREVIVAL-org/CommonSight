<?php

declare(strict_types=1);

namespace CommonSight\Model\Place;

use CommonSight\Model\Value\Coordinate;

/**
 * Master data of a measuring station whose current values come without name, position or flood stages
 * (data/stations.json of the plugin, ADR 0038).
 */
final readonly class Station
{
    /**
     * @param string|null $stageOf measured quantity the flood stages refer to: H water level, Q discharge
     * @param list<float> $stages flood stages in rising order (ČHMÚ: SPA 1 to 3)
     */
    public function __construct(
        public string $id,
        public string $name,
        public Coordinate $position,
        public ?string $river = null,
        public ?string $stageOf = null,
        public array $stages = [],
    ) {}
}
