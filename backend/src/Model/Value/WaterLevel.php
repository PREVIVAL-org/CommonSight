<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Water level with unit and reference height; values with different references are not comparable. */
final readonly class WaterLevel
{
    public function __construct(public float $value, public string $unit, public LevelReference $reference)
    {
        if (!is_finite($value) || $unit === '') {
            throw new \InvalidArgumentException('Invalid water level');
        }
    }
}
