<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Geo;

/** Determines the interior intervals of an area on horizontal scanlines (holes and islands taken into account). */
final class ScanlineIntervals
{
    /** Position of the scanlines between the southern and northern edge, checked in this order. */
    public const FRACTIONS = [0.5, 0.25, 0.75, 0.125, 0.875, 0.375, 0.625];

    /**
     * @param list<list<array{float, float}>> $rings
     * @return list<array{float, float, float}> intervals as [lonFrom, lonTo, lat]
     */
    public function intervals(array $rings, float $fraction): array
    {
        $lats = array_column($rings[0] ?? [], 1);
        if ($lats === []) {
            return [];
        }
        $lat = min($lats) + (max($lats) - min($lats)) * $fraction;
        $crossings = $this->crossings($rings, $lat);
        $intervals = [];
        for ($i = 0; $i + 1 < count($crossings); $i += 2) {
            $intervals[] = [$crossings[$i], $crossings[$i + 1], $lat];
        }

        return $intervals;
    }

    /**
     * @param list<list<array{float, float}>> $rings
     * @return list<float> sorted longitudes at which the scanline intersects a ring
     */
    private function crossings(array $rings, float $lat): array
    {
        $xs = [];
        foreach ($rings as $ring) {
            for ($i = 1, $count = count($ring); $i < $count; $i++) {
                [$x1, $y1] = $ring[$i - 1];
                [$x2, $y2] = $ring[$i];
                if (($y1 <= $lat && $y2 > $lat) || ($y2 <= $lat && $y1 > $lat)) {
                    $xs[] = $x1 + ($lat - $y1) * ($x2 - $x1) / ($y2 - $y1);
                }
            }
        }
        sort($xs, SORT_NUMERIC);

        return $xs;
    }
}
