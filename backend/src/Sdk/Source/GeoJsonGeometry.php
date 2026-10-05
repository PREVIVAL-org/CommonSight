<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Decoded;
use CommonSight\Model\Geometry;

/** Reads a GeoJSON geometry from source data; a missing or invalid geometry yields null. */
final class GeoJsonGeometry
{
    public function read(Decoded $geometry): ?Geometry
    {
        $type = $geometry->get('type')->string();
        $coordinates = $geometry->get('coordinates');
        if ($type === null || !$coordinates->isArray()) {
            return null;
        }
        try {
            return Geometry::fromGeoJson($type, $coordinates->array());
        } catch (\InvalidArgumentException) {
            return null; // The item stays visible without an area; the respective DeficitDetector counts the gap.
        }
    }
}
