<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoWeather;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\OpenMeteoWeather\Record\CurrentValues;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;

/** Maps the current weather values of a place to a model value: temperature, weather text, wind, humidity, precipitation (Q-WE-02). */
final class WeatherMapper implements ItemMapper
{
    private const FACTS = [['wind_speed_10m', 'source.open-meteo-weather.fact.wind', 'km/h'], ['relative_humidity_2m', 'source.open-meteo-weather.fact.humidity', '%'], ['precipitation', 'source.open-meteo-weather.fact.precipitation', 'mm']];

    public function __construct(private readonly WeatherCodeSummary $summary) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, CurrentValues::class);
        $facts = [];
        foreach (self::FACTS as [$name, $label, $unit]) {
            $value = $record->value($name);
            if ($value !== null) {
                $facts[] = new Fact(new Msg($label), $value, $unit);
            }
        }

        return new ModelValueItem(
            new ItemCommon(
                id: 'weather:' . $record->city->id,
                title: $record->city->name,
                url: 'https://open-meteo.com/',
                time: $record->time,
                position: $record->city->position,
                regionIds: $record->city->regionId === null ? [] : [$record->city->regionId],
                country: $record->city->foreignCountry,
            ),
            new CatalogTerm('temperature'),
            (float) $record->value('temperature_2m'),
            '°C',
            $this->summary->of($record->value('weather_code')),
            $facts,
        );
    }
}
