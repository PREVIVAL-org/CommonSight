<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen;

/** The pollen types of the CAMS model, named as in the Open-Meteo API (e.g. grass_pollen). */
enum PollenType: string
{
    case Alder = 'alder';
    case Birch = 'birch';
    case Grass = 'grass';
    case Mugwort = 'mugwort';
    case Olive = 'olive';
    case Ragweed = 'ragweed';

    public function apiName(): string
    {
        return $this->value . '_pollen';
    }
}
