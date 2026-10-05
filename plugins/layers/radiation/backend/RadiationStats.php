<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

use CommonSight\Model\Stats\LayerStats;

/** Color scale of the radiation layer with the display thresholds (Q-RA-05). */
final readonly class RadiationStats implements LayerStats
{
    public function __construct(public float $elevated, public float $high, public int $maxAgeHours) {}

    /** @return array{colorScale: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return ['colorScale' => [
            'elevated' => $this->elevated,
            'high' => $this->high,
            'unit' => 'µSv/h',
            'maxAgeHours' => $this->maxAgeHours,
            'origin' => 'display',
        ]];
    }
}
