<?php

declare(strict_types=1);

namespace CommonSight\Domain\Pipeline;

use CommonSight\Domain\Geo\NearbyFinder;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\Nearby;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\PipelineStep;

/**
 * Assigns each item the DACH countries and regions within the border radius (`vicinityKm` in config.php), its own country included: with a region selected,
 * the other regions of the same country belong to its border area as well (section "Grenzgebiet", ADR 0038). Items
 * without a position get none.
 */
final class NearbyAssigner implements PipelineStep
{
    public function __construct(private readonly NearbyFinder $finder) {}

    public function apply(array $items, UtcInstant $now): array
    {
        return array_map(function (Item $item): Item {
            $position = $item->common()->position;
            $near = $position === null ? new Nearby([], []) : $this->finder->near($position);

            return $item->withCommon($item->common()->withNear($near));
        }, $items);
    }
}
