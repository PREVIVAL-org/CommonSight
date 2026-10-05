<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\OpenMeteoPollen\Record\PollenValues;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/**
 * Maps the pollen of a place to a model value: the concentration of the strongest type, named in the summary, and
 * every type as a fact. No health assessment: the model values are shown as they are.
 */
final class PollenMapper implements ItemMapper
{
    private const UNIT = 'Pollen/m³';

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, PollenValues::class);
        [$type, $value] = $record->strongest();
        $facts = [];
        foreach ($record->concentrations as $name => $concentration) {
            $facts[] = new Fact(new Msg('source.open-meteo-pollen.type.' . $name), $concentration, self::UNIT);
        }

        return new ModelValueItem(
            new ItemCommon(
                id: 'pollen:' . $record->city->id,
                title: $record->city->name,
                url: 'https://open-meteo.com/en/docs/air-quality-api',
                time: $record->time,
                position: $record->city->position,
                regionIds: $record->city->regionId === null ? [] : [$record->city->regionId],
                country: $record->city->foreignCountry,
            ),
            new CatalogTerm('pollenConcentration'),
            $value,
            self::UNIT,
            $value > 0 ? new Msg('source.open-meteo-pollen.summary.strongest.' . $type) : new Msg('source.open-meteo-pollen.summary.none'),
            $facts,
        );
    }
}
