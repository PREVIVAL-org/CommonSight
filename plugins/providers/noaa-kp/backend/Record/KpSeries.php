<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaKp\Record;

use CommonSight\Model\Value\UtcInstant;

/** History of the planetary Kp index in 3-hour intervals, oldest first. */
final readonly class KpSeries
{
    /**
     * @param list<float> $values
     * @param list<UtcInstant> $times
     */
    public function __construct(public array $values, public array $times)
    {
        if ($values === [] || count($values) !== count($times)) {
            throw new \InvalidArgumentException('Kp series without values');
        }
    }
}
