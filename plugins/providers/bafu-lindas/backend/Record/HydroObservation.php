<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas\Record;

/** Latest observation of a BAFU station from LINDAS, variables as in the SPARQL query. */
final readonly class HydroObservation
{
    public function __construct(
        public string $id,
        public string $name,
        public string $waterName,
        public float $lon,
        public float $lat,
        public string $time,
        public ?float $level,
        public ?float $flow,
        public ?float $temperature,
        public ?int $danger,
    ) {}
}
