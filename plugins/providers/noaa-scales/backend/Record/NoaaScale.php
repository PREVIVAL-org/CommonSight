<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaScales\Record;

use CommonSight\Model\Value\UtcInstant;

/** Current level of a NOAA scale (G, R or S) from noaa-scales.json. */
final readonly class NoaaScale
{
    public function __construct(public string $letter, public int $Scale, public ?UtcInstant $stamp) {}
}
