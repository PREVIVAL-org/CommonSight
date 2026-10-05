<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Geo;

use CommonSight\Model\Geometry;

/**
 * Converts a GeoSphere warning area from EPSG:31287 to WGS84 (Q-W-AT-04). Open or degenerate rings are discarded: a
 * bad hole goes, a polygon with a bad outer ring goes, the rest of the area stays. Only an area without any valid
 * polygon is invalid.
 */
final class AustriaLambertGeometry
{
    public function __construct(private readonly AustriaLambertProjection $projection) {}

    /**
     * @param array<mixed> $coordinates
     * @throws \InvalidArgumentException for an area without a valid polygon
     * @throws ProjectionOutOfRange for points outside Austria
     */
    public function toWgs84(string $type, array $coordinates): Geometry
    {
        $polygons = match ($type) {
            'Polygon' => [$coordinates],
            'MultiPolygon' => $coordinates,
            default => throw new \InvalidArgumentException('GeoSphere warning area is not a polygon: ' . $type),
        };
        $converted = array_values(array_filter(array_map($this->polygon(...), array_values($polygons)), static fn(?array $p): bool => $p !== null));
        if ($converted === []) {
            throw new \InvalidArgumentException('GeoSphere warning area without a valid polygon');
        }

        return $type === 'Polygon' ? Geometry::fromGeoJson('Polygon', $converted[0]) : Geometry::fromGeoJson('MultiPolygon', $converted);
    }

    /** @return list<list<array{float, float}>>|null null if the outer ring is invalid */
    private function polygon(mixed $rings): ?array
    {
        if (!is_array($rings) || $rings === []) {
            return null;
        }
        $converted = array_map($this->ring(...), array_values($rings));
        if ($converted[0] === null) {
            return null;
        }

        return array_values(array_filter($converted, static fn(?array $ring): bool => $ring !== null));
    }

    /** @return list<array{float, float}>|null null for an open or degenerate ring */
    private function ring(mixed $ring): ?array
    {
        if (!is_array($ring) || count($ring) < 4) {
            return null;
        }
        $points = [];
        foreach (array_values($ring) as $point) {
            if (!is_array($point) || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)) {
                return null;
            }
            $points[] = $this->projection->toWgs84((float) $point[0], (float) $point[1])->toLonLat();
        }
        $closed = $points[0] === $points[count($points) - 1];
        $distinct = count(array_unique(array_map(static fn(array $p): string => $p[0] . ',' . $p[1], $points)));

        return $closed && $distinct >= 3 ? $points : null;
    }
}
