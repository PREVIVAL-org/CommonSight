<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Oeamtc;

use CommonSight\Model\Geometry;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\TrafficCategory;
use CommonSight\Model\Item\TrafficNoticeItem;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Plugin\Oeamtc\Record\TrafficEntry;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\SafeUrl;

/** Maps an ÖAMTC message to a traffic notice; more than one point yields a line (Q-TR-AT-02). */
final class OeamtcMapper implements ItemMapper
{
    private const FALLBACK_URL = 'https://www.oeamtc.at/verkehrsservice/';

    /** The categories of the feed (2026-10-04: Baustelle, Sperre, Stau, Verkehrsbehinderung); anything else is other. */
    private const CATEGORIES = [
        'stau' => TrafficCategory::Jam,
        'sperre' => TrafficCategory::Closure,
        'baustelle' => TrafficCategory::Roadworks,
    ];

    public function __construct(private readonly SafeUrl $url) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, TrafficEntry::class);
        // A broken line falls back to the point of the notice; a broken position leaves only the map out.
        $points = $this->points($record->line);
        if ($points === []) {
            $points = $this->points($record->point);
        }

        return new TrafficNoticeItem(
            new ItemCommon(
                id: 'oeamtc:' . $record->guid,
                title: $record->title !== '' ? $record->title : 'Verkehrsmeldung · ÖAMTC',
                url: $this->url->orFallback($record->link, self::FALLBACK_URL),
                time: $record->pubDate,
                position: $points[0] ?? null,
                geometry: count($points) > 1 ? Geometry::lineString($points) : null,
                lang: $record->language,
            ),
            null,
            $record->category !== '' ? $record->category : null,
            null,
            $record->description !== '' ? $record->description : null,
            self::CATEGORIES[mb_strtolower(trim($record->category))] ?? TrafficCategory::Other,
        );
    }

    /** @return list<Coordinate> */
    private function points(string $raw): array
    {
        $numbers = preg_split('/\s+/', trim($raw)) ?: [];
        if (count($numbers) < 2 || count($numbers) % 2 !== 0) {
            return [];
        }
        $points = [];
        for ($i = 0; $i < count($numbers); $i += 2) {
            if (!is_numeric($numbers[$i]) || !is_numeric($numbers[$i + 1])) {
                return [];
            }
            $point = Coordinate::tryFromLatLon((float) $numbers[$i], (float) $numbers[$i + 1]);
            if ($point === null) {
                return [];
            }
            $points[] = $point;
        }

        return $points;
    }
}
