<?php

declare(strict_types=1);

namespace CommonSight\Plugin\SpaceLayer;

use CommonSight\Model\Item\IndexItem;
use CommonSight\Model\Stats\LayerStats;
use CommonSight\Sdk\Layer\StatsBuilder;

/** Combines Kp with history and the NOAA scales G, R, S from the index items (Q-SP-04). */
final class SpaceStatsBuilder implements StatsBuilder
{
    public function build(array $items): LayerStats
    {
        $byId = [];
        foreach ($items as $item) {
            if ($item instanceof IndexItem) {
                $byId[$item->common->title] = $item;
            }
        }
        $kp = $byId['Kp'] ?? null;

        return new SpaceStats(
            $kp?->value,
            $kp === null ? [] : $kp->history ?? [],
            isset($byId['G']) ? (int) $byId['G']->value : null,
            isset($byId['R']) ? (int) $byId['R']->value : null,
            isset($byId['S']) ? (int) $byId['S']->value : null,
        );
    }
}
