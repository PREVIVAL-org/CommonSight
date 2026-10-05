<?php

declare(strict_types=1);

namespace CommonSight\Plugin\SpaceLayer;

use CommonSight\Model\Stats\LayerStats;

/** Current Kp, history and NOAA scales G, R, S (Q-SP-04). */
final readonly class SpaceStats implements LayerStats
{
    /** @param list<float> $history */
    public function __construct(
        public ?float $kp,
        public array $history,
        public ?int $g,
        public ?int $r,
        public ?int $s,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['kp' => $this->kp, 'history' => $this->history, 'G' => $this->g, 'R' => $this->r, 'S' => $this->s];
    }
}
