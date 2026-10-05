<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoWeather;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Place\City;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Place\CityDirectory;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the batch query for the current weather of all places of a scope at the Open-Meteo forecast API (Q-WE-01). */
final class OpenMeteoRequest implements SourceRequest
{
    public const SOURCE_ID = 'open-meteo-weather';

    public function __construct(private readonly CityDirectory $cities) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $cities = $this->cities->forCountry($scope);

        return [HttpRequest::withQuery('https://api.open-meteo.com/v1/forecast', [
            'latitude' => implode(',', array_map(static fn(City $c): string => (string) $c->position->lat, $cities)),
            'longitude' => implode(',', array_map(static fn(City $c): string => (string) $c->position->lon, $cities)),
            'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,precipitation',
            'timezone' => 'UTC',
        ], 'application/json', self::SOURCE_ID)];
    }
}
