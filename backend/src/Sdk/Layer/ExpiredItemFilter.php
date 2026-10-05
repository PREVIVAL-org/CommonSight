<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Value\UtcInstant;

/** Removes expired warnings (expires <= now, Q-00). */
final class ExpiredItemFilter implements PipelineStep
{
    public function apply(array $items, UtcInstant $now): array
    {
        return array_values(array_filter(
            $items,
            static fn(Item $item): bool => !$item instanceof WarningItem || $item->expires === null || $item->expires->isAfter($now),
        ));
    }
}
