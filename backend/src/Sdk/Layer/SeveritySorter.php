<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Value\UtcInstant;

/** Sorts warnings by severity and, for equal severity, by earliest onset (Q-W-01, Q-W-AT-11). */
final class SeveritySorter implements PipelineStep
{
    public function apply(array $items, UtcInstant $now): array
    {
        usort($items, fn(Item $a, Item $b): int => $this->rank($a) <=> $this->rank($b) ?: $this->start($a) <=> $this->start($b));

        return $items;
    }

    private function rank(Item $item): int
    {
        return $item instanceof WarningItem ? $item->severity->rank() : PHP_INT_MAX;
    }

    private function start(Item $item): int
    {
        $start = $item instanceof WarningItem ? ($item->onset ?? $item->common->time) : $item->common()->time;

        return $start->timestamp ?? PHP_INT_MAX;
    }
}
