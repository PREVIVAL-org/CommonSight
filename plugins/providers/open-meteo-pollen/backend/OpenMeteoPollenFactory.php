<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen;

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

/** Current pollen concentrations (CAMS model) at the places of the core, in the countries and the border zone, via Open-Meteo. */
final class OpenMeteoPollenFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: PollenRequest::SOURCE_ID,
            name: 'CAMS · Open-Meteo',
            attribution: new Attribution('Pollenflug: Open-Meteo.com (CC BY 4.0) auf Basis von CAMS (Copernicus Atmosphere Monitoring Service)', 'https://open-meteo.com/en/licence'),
            layer: 'pollen',
            scopes: [Scope::DE, Scope::AT, Scope::CH, Scope::Border],
            // Free API: fewer than 10,000 calls a day for all three Open-Meteo sources (PROFILE.md): 126 places every 3 h = 1,008.
            schedule: new SourceSchedule(3600, termsMinIntervalSec: 10800),
            // Outside the pollen season the model has no values: no items, no failure.
            expectations: new SourceExpectations(emptyIsFailure: false, rejectedMeansPartial: true),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $places = new CityDirectory([...$environment->data->cities(), ...$environment->data->borderPlaces()]);

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new PollenRequest($places),
            new PollenParser(new JsonBody(), new UtcTimeParser(), $places),
            new PollenMapper(),
            coverage: static fn(Scope $scope): Msg => new Msg('source.open-meteo-pollen.coverage', ['count' => count($places->forCountry($scope))]),
        )));
    }
}
