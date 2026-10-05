<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Discharge at a water gauge with the source's unit. */
final readonly class Discharge
{
    public function __construct(public float $value, public string $unit = 'm³/s')
    {
        if (!is_finite($value) || $unit === '') {
            throw new \InvalidArgumentException('Invalid discharge');
        }
    }
}
