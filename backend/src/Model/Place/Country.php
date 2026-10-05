<?php

declare(strict_types=1);

namespace CommonSight\Model\Place;

use CommonSight\Model\Value\Scope;

/** Country with its bounds (Appendix A); links and source names belong to the layer and source packages. */
final readonly class Country
{
    public function __construct(
        public Scope $scope,
        public string $name,
        public BoundingBox $bounds,
    ) {}
}
