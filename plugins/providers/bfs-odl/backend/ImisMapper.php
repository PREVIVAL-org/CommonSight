<?php

declare(strict_types=1);

namespace CommonSight\Plugin\BfsOdl;

use CommonSight\Model\Assessment;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Plugin\BfsOdl\Record\DoseRateStation;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Maps an ODL station to a measurement with averaging period; the layer classifies it (B-13, Q-RA-03). */
final class ImisMapper implements ItemMapper
{
    /** @param array<string, string> $infoUrls radiation info page per country (Appendix A) */
    public function __construct(private readonly UtcTimeParser $time, private readonly array $infoUrls) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, DoseRateStation::class);

        return new MeasurementItem(
            new ItemCommon(
                id: 'odl:' . $record->id,
                title: $record->name,
                url: $this->infoUrls[$context->scope->value] ?? 'https://www.imis.bfs.de/',
                time: $this->time->parseUtc($record->end_measure),
                position: Coordinate::fromLonLat($record->lon, $record->lat),
            ),
            new CatalogTerm('doseRate'),
            $record->value,
            $record->unit !== '' ? $record->unit : 'µSv/h',
            new Msg('reference.averaging', ['duration' => $record->duration !== '' ? $record->duration : '-']),
            Assessment::byLayer(),
        );
    }
}
