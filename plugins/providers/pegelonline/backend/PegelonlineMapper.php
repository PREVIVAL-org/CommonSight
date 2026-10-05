<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\LevelReference;
use CommonSight\Model\Value\WaterLevel;
use CommonSight\Plugin\Pegelonline\Record\Station;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Maps a PEGELONLINE station to a water level with assessment per B-10 (Q-WA-DE-03): above the local gauge zero (cm,
 * m+PNP), or above sea level where the gauge reports it so (m+NN, m ü. NHN; e.g. the canal gauges in Westphalia).
 */
final class PegelonlineMapper implements ItemMapper
{
    public function __construct(
        private readonly GermanWaterAssessor $assessor,
        private readonly UtcTimeParser $time,
        private readonly \DateTimeZone $sourceZone,
    ) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, Station::class);
        $level = new WaterLevel($record->value, $record->unit, self::reference($record->unit));
        $facts = $record->gaugeZeroValue === null ? [] : [new Fact(new Msg('source.pegelonline.fact.gaugeZero'), $record->gaugeZeroValue, $record->gaugeZeroUnit)];

        return new MeasurementItem(
            new ItemCommon(
                id: 'pegelonline:' . $record->uuid,
                title: implode(' · ', array_filter([$record->longname, $record->waterLongname])),
                url: 'https://www.pegelonline.wsv.de/gast/stammdaten?pegelnr=' . rawurlencode($record->number),
                time: $this->time->parse($record->timestamp, $this->sourceZone),
                position: Coordinate::fromLatLon($record->latitude, $record->longitude),
            ),
            new CatalogTerm('waterLevel'),
            $level->value,
            $level->unit,
            new Msg('reference.' . $level->reference->value),
            $this->assessor->assess($record->stateMnwMhw, $record->stateNswHsw),
            $facts,
        );
    }

    private static function reference(string $unit): LevelReference
    {
        return preg_match('/\b(NN|NHN)\b|ü\.?\s*A\b/u', $unit) === 1 ? LevelReference::SeaLevel : LevelReference::GaugeZero;
    }
}
