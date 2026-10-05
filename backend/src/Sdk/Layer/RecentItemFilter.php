<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Value\UtcInstant;

/** Keeps only items with a time within the time window (e.g. news of the last 72 hours, Q-NE-03). */
final class RecentItemFilter implements PipelineStep
{
    public function __construct(private readonly int $maxAgeSec) {}

    public function apply(array $items, UtcInstant $now): array
    {
        $oldest = $now->plusSeconds(-$this->maxAgeSec);

        return array_values(array_filter(
            $items,
            static fn(Item $item): bool => $item->common()->time !== null && !$item->common()->time->isBefore($oldest),
        ));
    }
}
