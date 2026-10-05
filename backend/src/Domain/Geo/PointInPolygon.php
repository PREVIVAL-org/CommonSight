<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

/** Checks whether a point lies in an area with holes (even-odd rule over all rings). */
final class PointInPolygon
{
    /** @param list<list<array{float, float}>> $rings outer ring and holes, positions [lon, lat] */
    public function inPolygon(array $rings, float $lon, float $lat): bool
    {
        $inside = false;
        foreach ($rings as $ring) {
            if ($this->crossesOddTimes($ring, $lon, $lat)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /** @param list<list<list<array{float, float}>>> $polygons */
    public function inAny(array $polygons, float $lon, float $lat): bool
    {
        foreach ($polygons as $rings) {
            if ($this->inPolygon($rings, $lon, $lat)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{float, float}> $ring */
    private function crossesOddTimes(array $ring, float $lon, float $lat): bool
    {
        $odd = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $odd = !$odd;
            }
        }

        return $odd;
    }
}
