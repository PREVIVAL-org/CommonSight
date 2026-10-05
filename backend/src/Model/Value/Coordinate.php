<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Point in WGS84 with named latitude and longitude; the source's order is stated explicitly on creation. */
final readonly class Coordinate
{
    private function __construct(public float $lat, public float $lon)
    {
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException(sprintf('Coordinate out of range: %F, %F', $lat, $lon));
        }
    }

    public static function fromLatLon(float $lat, float $lon): self
    {
        return new self($lat, $lon);
    }

    public static function fromLonLat(float $lon, float $lat): self
    {
        return new self($lat, $lon);
    }

    /** For parsers: an invalid position (out of range, not finite) rejects only its record, instead of throwing. */
    public static function tryFromLatLon(float $lat, float $lon): ?self
    {
        try {
            return new self($lat, $lon);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function tryFromLonLat(float $lon, float $lat): ?self
    {
        return self::tryFromLatLon($lat, $lon);
    }

    /** @return array{float, float} GeoJSON position [lon, lat] */
    public function toLonLat(): array
    {
        return [$this->lon, $this->lat];
    }
}
