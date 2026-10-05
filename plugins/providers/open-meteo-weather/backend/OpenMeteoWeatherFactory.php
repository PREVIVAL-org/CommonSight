<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoWeather;

use CommonSight\Model\Msg;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Place\CityDirectory;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Current weather at the places of the core, in the countries and the border zone, from Open-Meteo (Q-WE-*). */
final class OpenMeteoWeatherFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: OpenMeteoRequest::SOURCE_ID,
            name: 'Open-Meteo',
            attribution: new Attribution('Wetter: Open-Meteo.com (CC BY 4.0)', 'https://open-meteo.com/en/licence'),
            layer: 'weather',
            scopes: [Scope::DE, Scope::AT, Scope::CH, Scope::Border],
            // Free API: fewer than 10,000 calls a day, every place counts (PROFILE.md): 126 places every 30 min = 6,048.
            schedule: new SourceSchedule(900, termsMinIntervalSec: 1800),
            expectations: SourceExpectations::measurements(rejectedMeansPartial: true),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $places = new CityDirectory([...$environment->data->cities(), ...$environment->data->borderPlaces()]);

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new OpenMeteoRequest($places),
            new OpenMeteoParser(new JsonBody(), new UtcTimeParser(), $places, 'temperature_2m'),
            new WeatherMapper(new WeatherCodeSummary()),
            coverage: static fn(Scope $scope): Msg => new Msg('source.open-meteo-weather.coverage', ['count' => count($places->forCountry($scope))]),
        )));
    }
}
