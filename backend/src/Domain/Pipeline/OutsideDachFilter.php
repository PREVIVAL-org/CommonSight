<?php

declare(strict_types=1);

namespace CommonSight\Domain\Pipeline;

use CommonSight\Domain\Geo\RegionLocator;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\PipelineStep;

/**
 * Keeps in a snapshot of the border zone only items outside DACH: sources with a rectangle (earthquakes) also deliver
 * items inside, which the country snapshots already contain (ADR 0038). Items without a position are dropped.
 */
final class OutsideDachFilter implements PipelineStep
{
    /** @param list<RegionLocator> $locators one per DACH country */
    public function __construct(private readonly array $locators) {}

    public function apply(array $items, UtcInstant $now): array
    {
        return array_values(array_filter($items, function (Item $item): bool {
            $position = $item->common()->position;
            if ($position === null) {
                return false;
            }
            foreach ($this->locators as $locator) {
                if ($locator->regionsAt($position->lon, $position->lat) !== []) {
                    return false;
                }
            }

            return true;
        }));
    }
}
