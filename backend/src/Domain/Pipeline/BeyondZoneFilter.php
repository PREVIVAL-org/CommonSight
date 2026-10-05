<?php

declare(strict_types=1);

namespace CommonSight\Domain\Pipeline;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\PipelineStep;

/**
 * Drops in a snapshot of the border zone the items that are not near any DACH country: national sources (IMGW-PIB,
 * PAA) deliver their whole country, the border area ends at `vicinityKm` beyond DACH (ADR 0038). Runs after the NearbyAssigner.
 */
final class BeyondZoneFilter implements PipelineStep
{
    public function apply(array $items, UtcInstant $now): array
    {
        return array_values(array_filter($items, static fn(Item $item): bool => ($item->common()->near->countries ?? []) !== []));
    }
}
