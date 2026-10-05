<?php

declare(strict_types=1);

namespace CommonSight\Model\Place;

use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;

/**
 * Place or model point for weather and air: in DACH with its region (Appendix A), in the border zone with its
 * foreign country instead (scope border, contract/data/border-places.json).
 */
final readonly class City
{
    public function __construct(
        public string $id,
        public Scope $country,
        public string $name,
        public Coordinate $position,
        public ?RegionId $regionId,
        public ?string $foreignCountry = null,
    ) {}
}
