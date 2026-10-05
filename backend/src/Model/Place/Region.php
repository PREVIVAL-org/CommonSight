<?php

declare(strict_types=1);

namespace CommonSight\Model\Place;

use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;

/** State or canton with name, aliases, bounding rectangle and area. */
final readonly class Region
{
    /**
     * @param list<string> $aliases
     * @param list<list<list<array{float, float}>>> $polygons area as MultiPolygon coordinates [lon, lat]
     */
    public function __construct(
        public RegionId $id,
        public Scope $country,
        public string $name,
        public array $aliases,
        public BoundingBox $bbox,
        public Coordinate $refPoint,
        public array $polygons,
    ) {}

    /** @return list<string> name and aliases */
    public function names(): array
    {
        return [$this->name, ...$this->aliases];
    }
}
