<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Geo;

/** Determines a point inside an area: middle of the widest interior interval of the scanlines (Q-W-AT-07). */
final class InteriorPoint
{
    public function __construct(private readonly ScanlineIntervals $scanlines) {}

    /**
     * @param list<list<list<array{float, float}>>> $polygons
     * @return array{float, float}|null [lon, lat]
     */
    public function of(array $polygons): ?array
    {
        $best = null;
        $widest = 0.0;
        foreach ($polygons as $rings) {
            foreach (ScanlineIntervals::FRACTIONS as $fraction) {
                foreach ($this->scanlines->intervals($rings, $fraction) as [$from, $to, $lat]) {
                    if ($to - $from > $widest) {
                        $widest = $to - $from;
                        $best = [($from + $to) / 2, $lat];
                    }
                }
            }
        }

        return $best;
    }
}
