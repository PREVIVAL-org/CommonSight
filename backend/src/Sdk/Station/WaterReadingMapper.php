<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps a water level of the border zone to a measurement with the flood stages of its source (ADR 0038). */
final class WaterReadingMapper implements ItemMapper
{
    public function __construct(private readonly FloodStageAssessor $assessor) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, WaterReading::class);
        $facts = $record->discharge === null ? [] : [new Fact(new Msg('fact.discharge'), $record->discharge, 'm³/s')];

        return new MeasurementItem(
            new ItemCommon(
                id: $record->id,
                title: $record->title,
                url: $record->url,
                time: $record->time,
                position: $record->position,
                country: $record->country,
            ),
            new CatalogTerm('waterLevel'),
            $record->level,
            $record->unit,
            new Msg($record->reference),
            $this->assessor->assess($record->stages),
            $facts,
        );
    }
}
