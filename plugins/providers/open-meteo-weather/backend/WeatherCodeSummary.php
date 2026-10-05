<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoWeather;

use CommonSight\Model\Msg;

/** Translates a WMO weather code into a short text (Q-WE-03). */
final class WeatherCodeSummary
{
    public function of(?float $code): Msg
    {
        $key = match (true) {
            $code === null => 'unknown',
            $code <= 0 => 'clear',
            $code <= 3 => 'cloudy',
            $code <= 48 => 'fog',
            $code <= 67 => 'rain',
            $code <= 77 => 'snow',
            $code <= 82 => 'rainShowers',
            $code <= 86 => 'snowShowers',
            default => 'thunderstorm',
        };

        return new Msg('source.open-meteo-weather.summary.weather.' . $key);
    }
}
