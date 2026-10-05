<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoAir;

use CommonSight\Model\Msg;

/** Classifies an EU AQI: <= 20 good ... above 100 extremely poor (Q-AI-03). */
final class AirQualitySummary
{
    public function of(float $aqi): Msg
    {
        $key = match (true) {
            $aqi <= 20 => 'good',
            $aqi <= 40 => 'fair',
            $aqi <= 60 => 'moderate',
            $aqi <= 80 => 'poor',
            $aqi <= 100 => 'veryPoor',
            default => 'extremelyPoor',
        };

        return new Msg('source.open-meteo-air.summary.aqi.' . $key);
    }
}
