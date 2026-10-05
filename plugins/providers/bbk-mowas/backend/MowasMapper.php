<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Plugin\Mowas\Record\MowasWarning;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps a message of a NINA feed to a warning: civil protection, police or flood (Q-W-DE-04). */
final class MowasMapper implements ItemMapper
{
    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, MowasWarning::class);
        $common = new ItemCommon(
            id: $record->feed->idPrefix() . $record->id,
            title: $record->titleDe ?? 'Warnmeldung',
            url: 'https://warnung.bund.de/meldung/' . rawurlencode($record->id),
            source: $record->feed->label(),
            time: $record->startDate,
            geometry: $record->geometry,
            lang: 'de',
        );

        return new WarningItem(
            $common,
            $record->feed->hazard(),
            Severity::fromSource($record->severity),
            '',
            $record->startDate,
            $record->expiresDate,
            [],
            $record->feed->category(),
        );
    }
}
