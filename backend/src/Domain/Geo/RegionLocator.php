<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Model\Place\Region;

/** Finds the regions of a country that contain a point: via the grid, exactly at boundaries. */
final class RegionLocator
{
    /** @param list<Region> $regions in the order the grid refers to */
    public function __construct(
        private readonly RegionGrid $grid,
        private readonly array $regions,
        private readonly PointInPolygon $pointInPolygon,
    ) {}

    /** @return list<Region> */
    public function regionsAt(float $lon, float $lat): array
    {
        $cell = $this->grid->cellAt($lon, $lat);
        if ($cell === null) {
            return [];
        }
        $value = $this->grid->valueOf($cell);
        if ($value === RegionGrid::OUTSIDE) {
            return [];
        }
        if ($value !== RegionGrid::BOUNDARY) {
            return [$this->regions[$value]];
        }

        return $this->exact($this->grid->candidatesOf($cell), $lon, $lat);
    }

    /** @return list<Region> */
    public function regions(): array
    {
        return $this->regions;
    }

    /**
     * @param list<int> $candidates
     * @return list<Region>
     */
    private function exact(array $candidates, float $lon, float $lat): array
    {
        $found = [];
        foreach ($candidates as $index) {
            $region = $this->regions[$index];
            if ($this->pointInPolygon->inAny($region->polygons, $lon, $lat)) {
                $found[] = $region;
            }
        }

        return $found;
    }
}
