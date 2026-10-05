<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Geometry;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\GeoSphere\Record\WarnFeature;
use CommonSight\Sdk\Geo\AustriaLambertGeometry;
use CommonSight\Sdk\Geo\ProjectionOutOfRange;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps a GeoSphere warning (Q-W-AT-03, -05, -06, -13). */
final class GeoSphereWarningMapper implements ItemMapper
{
    /** @param array<int, string> $stateNames state code -> name */
    public function __construct(
        private readonly AustriaLambertGeometry $geometry,
        private readonly GeoSphereWarnType $types,
        private readonly array $stateNames,
    ) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, WarnFeature::class);
        $states = $this->states($record->gemeinden);
        $common = new ItemCommon(
            id: sprintf('geosphere:%s:%d:%d', $record->warnid, $record->start, $record->end),
            title: $this->types->title($record->wtype, $record->wlevel),
            url: 'https://warnungen.zamg.at/',
            time: $record->detail?->create,
            geometry: $this->projected($record),
            regionIds: array_map(RegionId::austrianState(...), $states),
            lang: 'de',
        );

        return new WarningItem(
            $common,
            new Msg($this->types->hazardKey($record->wtype)),
            $this->types->severity($record->wlevel),
            implode(', ', array_map(fn(int $digit): string => $this->stateNames[$digit] ?? '', $states)),
            UtcInstant::fromTimestamp($record->start),
            UtcInstant::fromTimestamp($record->end),
            $record->detail === null ? [] : $record->detail->sections,
            new CatalogTerm('weather'),
            $this->types->awareness($record->wlevel),
        );
    }

    private function projected(WarnFeature $record): ?Geometry
    {
        if ($record->geometryType === null || $record->coordinates === null) {
            return null;
        }
        try {
            return $this->geometry->toWgs84($record->geometryType, $record->coordinates);
        } catch (\InvalidArgumentException|ProjectionOutOfRange) {
            return null; // The warning stays in the list without an area, Q-W-AT-05.
        }
    }

    /**
     * @param list<string> $municipalities
     * @return list<int> state codes, sorted
     */
    private function states(array $municipalities): array
    {
        $digits = array_values(array_unique(array_map(static fn(string $code): int => (int) $code[0], $municipalities)));
        sort($digits);

        return $digits;
    }
}
