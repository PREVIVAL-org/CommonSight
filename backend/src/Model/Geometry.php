<?php

declare(strict_types=1);

namespace CommonSight\Model;

use CommonSight\Model\Value\Coordinate;

/**
 * GeoJSON geometry in WGS84 with positions [lon, lat]; validates the structure on creation (D-13).
 *
 * @phpstan-type Position array{float, float}
 */
final readonly class Geometry implements \JsonSerializable
{
    /** Depth of the position lists per type: 0 = one position, 1 = list, 2 = list of lists ... */
    private const DEPTH = ['Point' => 0, 'LineString' => 1, 'MultiLineString' => 2, 'Polygon' => 2, 'MultiPolygon' => 3];

    /** @param array<mixed> $coordinates */
    private function __construct(public string $type, public array $coordinates) {}

    /** @param array<mixed> $coordinates */
    public static function fromGeoJson(string $type, array $coordinates): self
    {
        $depth = self::DEPTH[$type] ?? throw new \InvalidArgumentException('Unsupported geometry type: ' . $type);

        return new self($type, self::normalize($coordinates, $depth, $type));
    }

    public static function point(Coordinate $point): self
    {
        return new self('Point', $point->toLonLat());
    }

    /** @param list<Coordinate> $points */
    public static function lineString(array $points): self
    {
        return self::fromGeoJson('LineString', array_map(static fn(Coordinate $c): array => $c->toLonLat(), $points));
    }

    /** @param list<list<Coordinate>> $rings */
    public static function polygon(array $rings): self
    {
        $coordinates = array_map(
            static fn(array $ring): array => array_map(static fn(Coordinate $c): array => $c->toLonLat(), $ring),
            $rings,
        );

        return self::fromGeoJson('Polygon', $coordinates);
    }

    /** @return list<list<list<array{float, float}>>> areas as a list of polygons, empty for points and lines */
    public function polygons(): array
    {
        /** @var list<list<list<array{float, float}>>> $polygons structure validated on creation (normalize) */
        $polygons = match ($this->type) {
            'Polygon' => [$this->coordinates],
            'MultiPolygon' => $this->coordinates,
            default => [],
        };

        return $polygons;
    }

    /** @return list<list<array{float, float}>> lines as a list of position lists, empty for other types */
    public function lines(): array
    {
        /** @var list<list<array{float, float}>> $lines structure validated on creation (normalize) */
        $lines = match ($this->type) {
            'LineString' => [$this->coordinates],
            'MultiLineString' => $this->coordinates,
            default => [],
        };

        return $lines;
    }

    /** @return array{float, float} position of a point */
    public function pointPosition(): array
    {
        if ($this->type !== 'Point') {
            throw new \LogicException('Geometry is not a point');
        }
        /** @var array{float, float} $position */
        $position = $this->coordinates;

        return $position;
    }

    /** @return array{type: string, coordinates: array<mixed>} */
    public function jsonSerialize(): array
    {
        return ['type' => $this->type, 'coordinates' => $this->coordinates];
    }

    /**
     * @param array<mixed> $coordinates
     * @return array<mixed>
     */
    private static function normalize(array $coordinates, int $depth, string $type): array
    {
        if ($depth === 0) {
            return self::position($coordinates);
        }
        $minimum = self::minimumCount($type, $depth);
        if (!array_is_list($coordinates) || count($coordinates) < $minimum) {
            throw new \InvalidArgumentException('Zu wenige Positionen in ' . $type);
        }

        return array_map(static fn(mixed $part): array => self::normalize(is_array($part) ? $part : [], $depth - 1, $type), $coordinates);
    }

    private static function minimumCount(string $type, int $depth): int
    {
        if ($depth !== 1) {
            return 1;
        }

        return $type === 'Polygon' || $type === 'MultiPolygon' ? 4 : 2;
    }

    /**
     * @param array<mixed> $position
     * @return array{float, float}
     */
    private static function position(array $position): array
    {
        $lon = $position[0] ?? null;
        $lat = $position[1] ?? null;
        if (!is_int($lon) && !is_float($lon) || !is_int($lat) && !is_float($lat)) {
            throw new \InvalidArgumentException('Invalid position');
        }

        return Coordinate::fromLonLat((float) $lon, (float) $lat)->toLonLat();
    }
}
