<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\UtcInstant;

/** Current ambient dose rate of a probe outside the BfS network, joined with its master data (ADR 0038). */
final readonly class DoseRateReading
{
    /** @param string|null $country foreign country (ISO 3166-1 alpha-2); null for a probe in DACH */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public ?string $country,
        public Coordinate $position,
        public ?UtcInstant $time,
        public float $value,
        public string $unit,
        public string $averaging,
    ) {}
}
