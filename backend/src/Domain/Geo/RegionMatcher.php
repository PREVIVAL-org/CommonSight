<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\RegionMatch;
use CommonSight\Model\Place\Region;
use CommonSight\Model\Value\RegionId;

/**
 * Assigns an item to the regions of its country, in the order geometry, point, source, area description (U-14, V2).
 */
final class RegionMatcher
{
    public function __construct(
        private readonly RegionLocator $locator,
        private readonly GeometrySamples $samples,
        private readonly PointInPolygon $pointInPolygon,
        private readonly AreaNameMatcher $areaNames,
    ) {}

    /** @return array{list<RegionId>, RegionMatch} */
    public function match(ItemCommon $common, ?string $area): array
    {
        $attempts = [
            [RegionMatch::Geometry, fn(): array => $this->byGeometry($common)],
            [RegionMatch::Point, fn(): array => $this->byPoint($common)],
            [RegionMatch::Source, fn(): array => $this->bySource($common)],
            [RegionMatch::Area, fn(): array => $this->areaNames->match($area, $this->locator->regions())],
        ];
        foreach ($attempts as [$match, $attempt]) {
            $regions = $attempt();
            if ($regions !== []) {
                return [$this->ids($regions), $match];
            }
        }

        return [[], RegionMatch::None];
    }

    /** @return list<Region> */
    private function byGeometry(ItemCommon $common): array
    {
        if ($common->geometry === null) {
            return [];
        }
        $found = [];
        foreach ($this->samples->of($common->geometry) as [$lon, $lat]) {
            foreach ($this->locator->regionsAt($lon, $lat) as $region) {
                $found[$region->id->value] = $region;
            }
        }
        // Small regions lying entirely within a large area (e.g. Berlin, Basel-Stadt).
        foreach ($this->locator->regions() as $region) {
            if (!isset($found[$region->id->value]) && $this->pointInPolygon->inAny($common->geometry->polygons(), $region->refPoint->lon, $region->refPoint->lat)) {
                $found[$region->id->value] = $region;
            }
        }

        return array_values($found);
    }

    /** @return list<Region> */
    private function byPoint(ItemCommon $common): array
    {
        if ($common->position === null) {
            return [];
        }

        return $this->locator->regionsAt($common->position->lon, $common->position->lat);
    }

    /** @return list<Region> */
    private function bySource(ItemCommon $common): array
    {
        $given = array_map(static fn(RegionId $id): string => $id->value, $common->regionIds);

        return array_values(array_filter($this->locator->regions(), static fn(Region $r): bool => in_array($r->id->value, $given, true)));
    }

    /**
     * @param list<Region> $regions
     * @return list<RegionId>
     */
    private function ids(array $regions): array
    {
        $ids = array_map(static fn(Region $r): string => $r->id->value, $regions);
        sort($ids);

        return array_map(RegionId::fromString(...), array_values(array_unique($ids)));
    }
}
