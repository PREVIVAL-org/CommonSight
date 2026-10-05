<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoAir;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Place\City;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Place\CityDirectory;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the batch query for the current air quality of all places of a scope at the Open-Meteo air quality API (Q-AI-01). */
final class OpenMeteoRequest implements SourceRequest
{
    public const SOURCE_ID = 'open-meteo-air';

    public function __construct(private readonly CityDirectory $cities) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $cities = $this->cities->forCountry($scope);

        return [HttpRequest::withQuery('https://air-quality-api.open-meteo.com/v1/air-quality', [
            'latitude' => implode(',', array_map(static fn(City $c): string => (string) $c->position->lat, $cities)),
            'longitude' => implode(',', array_map(static fn(City $c): string => (string) $c->position->lon, $cities)),
            'current' => 'european_aqi,pm2_5,pm10',
            'timezone' => 'UTC',
        ], 'application/json', self::SOURCE_ID)];
    }
}
