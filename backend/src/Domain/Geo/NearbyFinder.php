<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Model\Item\Nearby;
use CommonSight\Model\Place\BoundingBox;
use CommonSight\Model\Place\Region;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\Scope;

/**
 * Finds the DACH regions within a distance of a point, and their countries; one rule for map and lists: everything
 * within `vicinityKm` (config.php) of the selected country or region (ADR 0038).
 *
 * Each region is represented by sample points from its grid (every 0.1°, about 10 km) plus its reference point, so
 * that even the smallest region (Basel-Stadt, Appenzell) has one. Only regions whose bounding box lies within reach
 * are checked, and only until the first sample within the distance: accurate to about 7 km, fast for thousands of
 * points.
 */
final class NearbyFinder
{
    private const KM_PER_DEGREE = 111.2;
    /** Every second grid cell (grid 0.05°) */
    private const SAMPLE_STEP = 2;

    /** @var array<string, array<int, list<array{float, float}>>>|null samples [lat, lon] per country and region index */
    private ?array $samples = null;

    /** @param array<string, array{RegionGrid, list<Region>}> $countries grid and regions per country, in the order DE, AT, CH */
    public function __construct(
        private readonly array $countries,
        private readonly float $km = 300.0,
    ) {}

    /** All DACH countries and regions in reach, the point's own country included. */
    public function near(Coordinate $point): Nearby
    {
        $this->samples ??= $this->sampleAll();
        $countries = [];
        $regionIds = [];
        foreach ($this->countries as $code => [, $regions]) {
            $found = [];
            foreach ($regions as $index => $region) {
                if ($this->boxDistance($point, $region->bbox) <= $this->km && $this->anyWithin($point, $this->samples[$code][$index] ?? [])) {
                    $found[] = $region->id;
                }
            }
            if ($found !== []) {
                $countries[] = Scope::from($code);
                array_push($regionIds, ...$found);
            }
        }

        return new Nearby($countries, $regionIds);
    }

    /** @param list<array{float, float}> $samples */
    private function anyWithin(Coordinate $point, array $samples): bool
    {
        foreach ($samples as [$lat, $lon]) {
            if ($this->distance($point, $lat, $lon) <= $this->km) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<int, list<array{float, float}>>> */
    private function sampleAll(): array
    {
        $samples = [];
        foreach ($this->countries as $code => [$grid, $regions]) {
            $samples[$code] = $this->sampleCountry($grid, $regions);
        }

        return $samples;
    }

    /**
     * @param list<Region> $regions
     * @return array<int, list<array{float, float}>>
     */
    private function sampleCountry(RegionGrid $grid, array $regions): array
    {
        $samples = [];
        foreach ($regions as $index => $region) {
            $samples[$index] = [[$region->refPoint->lat, $region->refPoint->lon]];
        }
        foreach ($this->sampledCells($grid) as [$value, $candidates, $lat, $lon]) {
            foreach ($value === RegionGrid::BOUNDARY ? $candidates : [$value] as $index) {
                $samples[$index][] = [$lat, $lon];
            }
        }

        return $samples;
    }

    /** @return \Generator<array{int, list<int>, float, float}> value, candidates and centre of every sampled cell inside the country */
    private function sampledCells(RegionGrid $grid): \Generator
    {
        for ($row = 0; $row < $grid->rows; $row += self::SAMPLE_STEP) {
            $lat = $grid->south + ($row + 0.5) * $grid->cellSize;
            for ($col = 0; $col < $grid->cols; $col += self::SAMPLE_STEP) {
                $cell = $row * $grid->cols + $col;
                $value = $grid->valueOf($cell);
                if ($value !== RegionGrid::OUTSIDE) {
                    yield [$value, $grid->candidatesOf($cell), $lat, $grid->west + ($col + 0.5) * $grid->cellSize];
                }
            }
        }
    }

    /** Distance to the nearest point of the box; 0 inside. */
    private function boxDistance(Coordinate $point, BoundingBox $box): float
    {
        $lat = max($box->south, min($box->north, $point->lat));
        $lon = max($box->west, min($box->east, $point->lon));

        return $this->distance($point, $lat, $lon);
    }

    private function distance(Coordinate $point, float $lat, float $lon): float
    {
        $kmPerLon = self::KM_PER_DEGREE * cos(deg2rad(($lat + $point->lat) / 2));

        return hypot(($lat - $point->lat) * self::KM_PER_DEGREE, ($lon - $point->lon) * $kmPerLon);
    }
}
