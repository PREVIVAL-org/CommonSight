<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs\Record;

use CommonSight\Model\Value\UtcInstant;

/** An earthquake from the USGS event catalog (GeoJSON, coordinates lon, lat, depth). */
final readonly class Quake
{
    public function __construct(
        public string $id,
        public string $title,
        public float $mag,
        public string $place,
        public ?UtcInstant $time,
        public ?string $url,
        public float $lon,
        public float $lat,
        public float $depth,
    ) {}
}
