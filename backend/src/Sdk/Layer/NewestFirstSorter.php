<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Value\UtcInstant;

/** Sorts items by time, newest first; items without a time at the end. */
final class NewestFirstSorter implements PipelineStep
{
    public function apply(array $items, UtcInstant $now): array
    {
        usort($items, static fn(Item $a, Item $b): int => ($b->common()->time->timestamp ?? PHP_INT_MIN) <=> ($a->common()->time->timestamp ?? PHP_INT_MIN));

        return $items;
    }
}
