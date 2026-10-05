<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\UtcInstant;

/**
 * Current water level of a gauge in the border zone, already joined with its master data; the parser of each
 * source fills it, WaterReadingMapper turns it into an item (ADR 0038).
 */
final readonly class WaterReading
{
    /**
     * @param string $reference message key of the reference height, e.g. reference.gaugeZero
     * @param float|null $discharge discharge in m³/s at the same gauge, if the source delivers it
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $country,
        public Coordinate $position,
        public ?UtcInstant $time,
        public float $level,
        public string $unit,
        public string $reference,
        public FloodStages $stages,
        public ?float $discharge = null,
    ) {}
}
