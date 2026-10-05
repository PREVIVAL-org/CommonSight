<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

/** Display thresholds for radiation in µSv/h; finite, positive, "high" above "elevated" (B-14). */
final readonly class RadiationThresholds
{
    public function __construct(public float $elevated = 0.3, public float $high = 1.0)
    {
        if (!is_finite($elevated) || !is_finite($high) || $elevated <= 0 || $high <= $elevated) {
            throw new \InvalidArgumentException('Radiation thresholds must be finite, positive and ascending.');
        }
    }
}
