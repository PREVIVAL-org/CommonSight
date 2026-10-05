<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Geo;

use CommonSight\Model\Geometry;

/** Rounds the coordinates of a geometry to a fixed number of decimal places (Architecture 4.8). */
final class GeometryRounding
{
    public function __construct(private readonly int $decimals = 5) {}

    public function round(Geometry $geometry): Geometry
    {
        return Geometry::fromGeoJson($geometry->type, $this->roundNested($geometry->coordinates));
    }

    /**
     * @param array<mixed> $coordinates
     * @return array<mixed>
     */
    private function roundNested(array $coordinates): array
    {
        return array_map(
            fn(mixed $part): mixed => is_array($part) ? $this->roundNested($part) : (is_float($part) || is_int($part) ? round($part, $this->decimals) : $part),
            $coordinates,
        );
    }
}
