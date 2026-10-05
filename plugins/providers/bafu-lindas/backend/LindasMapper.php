<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\Discharge;
use CommonSight\Model\Value\LevelReference;
use CommonSight\Model\Value\WaterLevel;
use CommonSight\Plugin\Lindas\Record\HydroObservation;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Maps a BAFU observation to water level in "m ü. M." or, without it, to discharge; assessment per B-12 (Q-WA-CH-02 to -04). */
final class LindasMapper implements ItemMapper
{
    public function __construct(private readonly SwissWaterAssessor $assessor, private readonly UtcTimeParser $time) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, HydroObservation::class);
        $common = new ItemCommon(
            id: 'bafu:' . $record->id,
            title: implode(' · ', array_filter([$record->name, $record->waterName])),
            url: 'https://www.hydrodaten.admin.ch/de/aktuelle-lage',
            time: $this->time->parseUtc($record->time),
            position: Coordinate::fromLonLat($record->lon, $record->lat),
        );
        $assessment = $this->assessor->assess($record->danger);
        if ($record->level !== null) {
            $level = new WaterLevel($record->level, 'm ü. M.', LevelReference::SeaLevel);

            return new MeasurementItem($common, new CatalogTerm('waterLevel'), $level->value, $level->unit, new Msg('reference.seaLevel'), $assessment, $this->facts($record, true));
        }
        $discharge = new Discharge((float) $record->flow);

        return new MeasurementItem($common, new CatalogTerm('discharge'), $discharge->value, $discharge->unit, new Msg('reference.dischargeAtGauge'), $assessment, $this->facts($record, false));
    }

    /** @return list<Fact> */
    private function facts(HydroObservation $record, bool $withDischarge): array
    {
        $facts = [];
        if ($withDischarge && $record->flow !== null) {
            $facts[] = new Fact(new Msg('fact.discharge'), $record->flow, 'm³/s');
        }
        if ($record->temperature !== null) {
            $facts[] = new Fact(new Msg('source.bafu-lindas.fact.waterTemperature'), $record->temperature, '°C');
        }

        return $facts;
    }
}
