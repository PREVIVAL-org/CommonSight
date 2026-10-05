<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoAir;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\OpenMeteoAir\Record\CurrentValues;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps the current air quality of a place to a model value: EU AQI with classification, PM2.5 and PM10 (Q-AI-02). */
final class AirQualityMapper implements ItemMapper
{
    private const FACTS = [['pm2_5', 'source.open-meteo-air.fact.pm25'], ['pm10', 'source.open-meteo-air.fact.pm10']];

    public function __construct(private readonly AirQualitySummary $summary) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, CurrentValues::class);
        $aqi = (float) $record->value('european_aqi');
        $facts = [];
        foreach (self::FACTS as [$name, $label]) {
            $value = $record->value($name);
            if ($value !== null) {
                $facts[] = new Fact(new Msg($label), $value, 'µg/m³');
            }
        }

        return new ModelValueItem(
            new ItemCommon(
                id: 'air:' . $record->city->id,
                title: $record->city->name,
                url: 'https://open-meteo.com/en/docs/air-quality-api',
                time: $record->time,
                position: $record->city->position,
                regionIds: $record->city->regionId === null ? [] : [$record->city->regionId],
                country: $record->city->foreignCountry,
            ),
            new CatalogTerm('airQualityIndex'),
            $aqi,
            'EU-AQI',
            $this->summary->of($aqi),
            $facts,
        );
    }
}
