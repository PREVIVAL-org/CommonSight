<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Model\Geometry;

/** Chooses the points of a geometry at which the region assignment is checked. */
final class GeometrySamples
{
    public function __construct(private readonly InteriorSamples $interior) {}

    /** @return list<array{float, float}> points [lon, lat] */
    public function of(Geometry $geometry): array
    {
        return match ($geometry->type) {
            'Point' => [$geometry->pointPosition()],
            'LineString', 'MultiLineString' => $this->lineSamples($geometry->lines()),
            default => $this->interior->of($geometry->polygons()),
        };
    }

    /**
     * @param list<list<array{float, float}>> $lines
     * @return list<array{float, float}>
     */
    private function lineSamples(array $lines): array
    {
        $samples = [];
        foreach ($lines as $line) {
            foreach ($line as $index => $position) {
                $samples[] = $position;
                if ($index > 0) {
                    $previous = $line[$index - 1];
                    $samples[] = [($previous[0] + $position[0]) / 2, ($previous[1] + $position[1]) / 2];
                }
            }
        }

        return $samples;
    }
}
