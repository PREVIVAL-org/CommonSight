<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Eurdep\Record;

/** A measuring station from the BfS/IMIS WFS with its latest ambient dose rate value. */
final readonly class DoseRateStation
{
    public function __construct(
        public string $id,
        public string $name,
        public float $lon,
        public float $lat,
        public float $value,
        public string $unit,
        public ?string $end_measure,
        public string $duration,
    ) {}
}
