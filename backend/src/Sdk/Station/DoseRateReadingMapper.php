<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Assessment;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps a dose rate outside the BfS network to a measurement; the layer classifies it (B-13, ADR 0038). */
final class DoseRateReadingMapper implements ItemMapper
{
    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, DoseRateReading::class);

        return new MeasurementItem(
            new ItemCommon(
                id: $record->id,
                title: $record->title,
                url: $record->url,
                time: $record->time,
                position: $record->position,
                country: $record->country,
            ),
            new CatalogTerm('doseRate'),
            $record->value,
            $record->unit,
            new Msg('reference.averaging', ['duration' => $record->averaging]),
            Assessment::byLayer(),
        );
    }
}
