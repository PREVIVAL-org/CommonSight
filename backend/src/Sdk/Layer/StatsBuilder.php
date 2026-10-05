<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Stats\LayerStats;

/** Determines the layer-specific key figures from the items. */
interface StatsBuilder
{
    /** @param list<Item> $items */
    public function build(array $items): LayerStats;
}
