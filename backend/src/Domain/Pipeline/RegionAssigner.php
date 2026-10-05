<?php

declare(strict_types=1);

namespace CommonSight\Domain\Pipeline;

use CommonSight\Domain\Geo\RegionMatcher;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\PipelineStep;

/** Assigns each item the regions of its country (U-14, V2). */
final class RegionAssigner implements PipelineStep
{
    public function __construct(private readonly RegionMatcher $matcher) {}

    public function apply(array $items, UtcInstant $now): array
    {
        return array_map(function (Item $item): Item {
            $area = $item instanceof WarningItem ? $item->area : null;
            [$regionIds, $match] = $this->matcher->match($item->common(), $area);

            return $item->withCommon($item->common()->withRegions($regionIds, $match));
        }, $items);
    }
}
