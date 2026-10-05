<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Autobahn;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\TrafficCategory;
use CommonSight\Model\Item\TrafficNoticeItem;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Plugin\Autobahn\Record\RoadWarning;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Maps an Autobahn warning, closure or short-term roadworks to a traffic notice with route, kind, start and position
 * (Q-TR-DE-02). The kind in German: the API names it with English codes.
 */
final class AutobahnMapper implements ItemMapper
{
    /** Closures and roadworks by their display type: German word and category. */
    private const DISPLAY_TYPES = [
        'CLOSURE' => ['Sperrung', TrafficCategory::Closure],
        'CLOSURE_ENTRY_EXIT' => ['Gesperrte Anschlussstelle', TrafficCategory::Closure],
        'SHORT_TERM_ROADWORKS' => ['Tagesbaustelle', TrafficCategory::Roadworks],
        'ROADWORKS' => ['Baustelle', TrafficCategory::Roadworks],
    ];

    /** Warnings by their kind of traffic disruption (DATEX II); all of them jams. */
    private const TRAFFIC_TYPES = [
        'QUEUING_TRAFFIC' => 'Stau',
        'STATIONARY_TRAFFIC' => 'Stillstand',
        'SLOW_TRAFFIC' => 'Stockender Verkehr',
        'HEAVY_TRAFFIC' => 'Dichter Verkehr',
    ];

    public function __construct(private readonly UtcTimeParser $time) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, RoadWarning::class);
        $start = $this->time->parseUtc($record->startTimestamp);
        [$kind, $category] = $this->kind($record);

        return new TrafficNoticeItem(
            new ItemCommon(
                id: 'autobahn:' . $record->identifier,
                title: $record->title !== '' ? $record->title : 'Verkehrsmeldung',
                url: 'https://www.autobahn.de/unterwegs',
                time: $start,
                position: $this->position($record->point),
                lang: 'de',
            ),
            $record->subtitle !== '' ? $record->subtitle : null,
            $kind,
            $start,
            $record->description === [] ? null : implode(' ', $record->description),
            $category,
        );
    }

    /**
     * A closure or roadworks by its display type, a warning by its traffic type; any other code as a disruption.
     *
     * @return array{?string, TrafficCategory}
     */
    private function kind(RoadWarning $record): array
    {
        if (isset(self::DISPLAY_TYPES[$record->displayType ?? ''])) {
            return self::DISPLAY_TYPES[$record->displayType];
        }
        if ($record->abnormalTrafficType === null) {
            return [null, TrafficCategory::Other];
        }
        $jam = self::TRAFFIC_TYPES[$record->abnormalTrafficType] ?? null;

        return $jam === null ? ['Verkehrsstörung', TrafficCategory::Other] : [$jam, TrafficCategory::Jam];
    }

    private function position(?string $point): ?Coordinate
    {
        $parts = explode(',', (string) $point);
        if (count($parts) !== 2 || !is_numeric(trim($parts[0])) || !is_numeric(trim($parts[1]))) {
            return null;
        }
        try {
            return Coordinate::fromLatLon((float) $parts[0], (float) $parts[1]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
