<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline\Record;

/** A PEGELONLINE station with its time series W (water level), only the required fields (Q-WA-DE-02). */
final readonly class Station
{
    public function __construct(
        public string $uuid,
        public string $number,
        public string $longname,
        public string $waterLongname,
        public float $latitude,
        public float $longitude,
        public string $unit,
        public float $value,
        public string $timestamp,
        public ?string $stateMnwMhw,
        public ?string $stateNswHsw,
        public ?float $gaugeZeroValue,
        public ?string $gaugeZeroUnit,
    ) {}
}
