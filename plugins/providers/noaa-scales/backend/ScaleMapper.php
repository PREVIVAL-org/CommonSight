<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaScales;

use CommonSight\Model\Item\IndexItem;
use CommonSight\Model\Item\IndexScale;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Msg;
use CommonSight\Plugin\NoaaScales\Record\NoaaScale;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps a NOAA scale to an index 0-5; for S with the note on particles in space (Q-SP-03). */
final class ScaleMapper implements ItemMapper
{
    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, NoaaScale::class);
        $description = $record->letter === 'S'
            ? new Msg('source.noaa-scales.index.S.description')
            : new Msg('source.noaa-scales.index.noaa.description', ['scale' => $record->letter]);

        return new IndexItem(
            new ItemCommon(
                id: 'noaa-' . $record->letter,
                title: $record->letter,
                url: 'https://www.swpc.noaa.gov/noaa-scales-explanation',
                time: $record->stamp,
            ),
            new Msg('source.noaa-scales.index.' . $record->letter . '.name'),
            $record->Scale,
            new IndexScale(0, 5, new Msg('source.noaa-scales.scale.noaa', ['scale' => $record->letter])),
            $description,
        );
    }
}
