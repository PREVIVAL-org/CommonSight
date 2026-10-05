<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Value\UtcInstant;

/** Limits the number of items of a layer (e.g. 45 news items, Q-NE-03). */
final class ItemLimit implements PipelineStep
{
    public function __construct(private readonly int $maximum) {}

    public function apply(array $items, UtcInstant $now): array
    {
        return array_slice($items, 0, $this->maximum);
    }
}
