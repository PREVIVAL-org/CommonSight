<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaKp;

use CommonSight\Model\Item\IndexItem;
use CommonSight\Model\Item\IndexScale;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Msg;
use CommonSight\Plugin\NoaaKp\Record\KpSeries;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps the Kp history to the index of the latest value with the last 16 values as history (Q-SP-03, Q-SP-04). */
final class KpMapper implements ItemMapper
{
    private const HISTORY_LENGTH = 16;

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, KpSeries::class);
        $last = count($record->values) - 1;

        return new IndexItem(
            new ItemCommon(
                id: 'kp',
                title: 'Kp',
                url: 'https://www.swpc.noaa.gov/products/planetary-k-index',
                time: $record->times[$last],
            ),
            new Msg('source.noaa-kp.index.kp.name'),
            $record->values[$last],
            new IndexScale(0, 9, new Msg('source.noaa-kp.scale.kp')),
            new Msg('source.noaa-kp.index.kp.description'),
            array_slice($record->values, -self::HISTORY_LENGTH),
        );
    }
}
