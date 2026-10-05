<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoAir\Record;

use CommonSight\Model\Place\City;
use CommonSight\Model\Value\UtcInstant;

/** Current values (block current) from Open-Meteo for a place, keys as in the API. */
final readonly class CurrentValues
{
    /** @param array<string, float> $values e.g. temperature_2m, weather_code, european_aqi */
    public function __construct(public City $city, public ?UtcInstant $time, public array $values) {}

    public function value(string $name): ?float
    {
        return $this->values[$name] ?? null;
    }
}
