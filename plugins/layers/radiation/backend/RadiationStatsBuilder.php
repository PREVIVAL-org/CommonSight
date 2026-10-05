<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

use CommonSight\Model\Stats\LayerStats;
use CommonSight\Sdk\Layer\StatsBuilder;

/** Provides the radiation color scale from the configured thresholds (Q-RA-05). */
final class RadiationStatsBuilder implements StatsBuilder
{
    public function __construct(private readonly RadiationThresholds $thresholds, private readonly int $maxAgeHours) {}

    public function build(array $items): LayerStats
    {
        return new RadiationStats($this->thresholds->elevated, $this->thresholds->high, $this->maxAgeHours);
    }
}
