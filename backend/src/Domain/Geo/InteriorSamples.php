<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Sdk\Geo\ScanlineIntervals;

/** Chooses sample points inside an area, away from its edge, for the region assignment. */
final class InteriorSamples
{
    public function __construct(private readonly ScanlineIntervals $scanlines) {}

    /**
     * @param list<list<list<array{float, float}>>> $polygons
     * @return list<array{float, float}> points [lon, lat]
     */
    public function of(array $polygons): array
    {
        $samples = [];
        foreach ($polygons as $rings) {
            foreach (ScanlineIntervals::FRACTIONS as $fraction) {
                foreach ($this->scanlines->intervals($rings, $fraction) as [$from, $to, $lat]) {
                    $width = $to - $from;
                    $samples[] = [$from + $width * 0.5, $lat];
                    $samples[] = [$from + $width * 0.2, $lat];
                    $samples[] = [$from + $width * 0.8, $lat];
                }
            }
        }

        return $samples;
    }
}
