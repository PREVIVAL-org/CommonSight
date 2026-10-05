<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Alertswiss;

use CommonSight\Model\Decoded;
use CommonSight\Model\Geometry;
use CommonSight\Sdk\Geo\GeometryRounding;

/**
 * The area of an alert: its polygons (points as ["lat", "lon"] strings) and its circles (centre and radius in km, as in
 * CAP) as one polygon or multipolygon. Broken polygons are left out; an alert for the whole country has none.
 */
final class AlertAreas
{
    /** Corners of the polygon that stands for a circle. */
    private const CIRCLE_POINTS = 24;
    private const KM_PER_DEGREE = 111.32;

    public function __construct(private readonly GeometryRounding $rounding) {}

    /** @param list<Decoded> $areas */
    public function geometry(array $areas): ?Geometry
    {
        $polygons = [];
        foreach ($areas as $area) {
            foreach ($area->get('polygons')->list() as $polygon) {
                $polygons[] = $this->ring($polygon->get('coordinates')->list());
            }
            foreach ($area->get('circles')->list() as $circle) {
                $polygons[] = $this->circle($circle);
            }
        }
        $polygons = array_values(array_filter($polygons, static fn(?array $ring): bool => $ring !== null));
        if ($polygons === []) {
            return null;
        }
        $geometry = count($polygons) === 1
            ? Geometry::fromGeoJson('Polygon', [$polygons[0]])
            : Geometry::fromGeoJson('MultiPolygon', array_map(static fn(array $ring): array => [$ring], $polygons));

        return $this->rounding->round($geometry);
    }

    /**
     * @param list<Decoded> $points
     * @return list<array{float, float}>|null closed ring in lon/lat, null if it is not a valid polygon
     */
    private function ring(array $points): ?array
    {
        $ring = [];
        foreach ($points as $point) {
            $lat = $point->get(0)->raw();
            $lon = $point->get(1)->raw();
            if (!is_numeric($lat) || !is_numeric($lon)) {
                return null;
            }
            $ring[] = [(float) $lon, (float) $lat];
        }
        if (count($ring) >= 3 && $ring[0] !== $ring[count($ring) - 1]) {
            $ring[] = $ring[0];
        }

        return $this->valid($ring);
    }

    /** @return list<array{float, float}>|null */
    private function circle(Decoded $circle): ?array
    {
        $lat = $circle->get('centerPosition', 0)->raw();
        $lon = $circle->get('centerPosition', 1)->raw();
        $radius = $circle->get('radius')->raw();
        if (!is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius) || (float) $radius <= 0) {
            return null;
        }
        $dLat = (float) $radius / self::KM_PER_DEGREE;
        $dLon = (float) $radius / (self::KM_PER_DEGREE * cos(deg2rad((float) $lat)));
        $ring = [];
        for ($i = 0; $i < self::CIRCLE_POINTS; $i++) {
            $angle = 2 * M_PI * $i / self::CIRCLE_POINTS;
            $ring[] = [(float) $lon + $dLon * cos($angle), (float) $lat + $dLat * sin($angle)];
        }
        $ring[] = $ring[0];

        return $this->valid($ring);
    }

    /**
     * @param list<array{float, float}> $ring
     * @return list<array{float, float}>|null
     */
    private function valid(array $ring): ?array
    {
        try {
            Geometry::fromGeoJson('Polygon', [$ring]);

            return $ring;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
