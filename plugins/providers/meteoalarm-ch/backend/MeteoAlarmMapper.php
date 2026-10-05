<?php

declare(strict_types=1);

namespace CommonSight\Plugin\MeteoAlarm;

use CommonSight\Model\Geometry;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\SectionHeading;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\MeteoAlarm\Record\CapEntry;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Text\SafeUrl;

/** Maps a MeteoAlarm entry to a warning; the CAP polygon (pairs "lat,lon") is flipped to GeoJSON (Q-W-CH-02, Q-W-CH-03). */
final class MeteoAlarmMapper implements ItemMapper
{
    private const FALLBACK_URL = 'https://www.naturgefahren.ch/';

    public function __construct(private readonly SafeUrl $url, private readonly WarningSections $sections) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, CapEntry::class);
        $common = new ItemCommon(
            id: 'meteoalarm:' . $record->id,
            title: $record->headline !== '' ? $record->headline : $record->title,
            url: $this->url->orFallback($record->alternateLink, self::FALLBACK_URL),
            time: $record->updated,
            geometry: $this->polygon($record->polygon),
            lang: $record->language,
        );

        return new WarningItem(
            $common,
            $record->event !== '' ? new Msg('hazard.source', ['text' => $record->event]) : new Msg('hazard.weather'),
            Severity::fromSource($record->severity),
            $record->areaDesc,
            $record->onset,
            $record->expires,
            $this->sections->of([SectionHeading::Description->value => $record->description, SectionHeading::Advice->value => $record->instruction]),
            new CatalogTerm('weather'),
        );
    }

    private function polygon(string $raw): ?Geometry
    {
        $ring = [];
        foreach (preg_split('/\s+/', trim($raw)) ?: [] as $pair) {
            $parts = explode(',', $pair);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $ring[] = [(float) $parts[1], (float) $parts[0]];
            }
        }
        if (count($ring) < 3) {
            return null;
        }
        if ($ring[0] !== $ring[count($ring) - 1]) {
            $ring[] = $ring[0];
        }
        try {
            return Geometry::fromGeoJson('Polygon', [$ring]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
