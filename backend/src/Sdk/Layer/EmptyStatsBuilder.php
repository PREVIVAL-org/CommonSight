<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Stats\EmptyStats;
use CommonSight\Model\Stats\LayerStats;

/** Key figures for layers without their own key figures. */
final class EmptyStatsBuilder implements StatsBuilder
{
    public function build(array $items): LayerStats
    {
        return new EmptyStats();
    }
}
