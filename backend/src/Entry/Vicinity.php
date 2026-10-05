<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Infrastructure\Contract\GeneratedData;

/**
 * The vicinity ("Umkreis") of config.php, checked against the map area of the build: beyond `mapKm` there are no
 * map, tiles or border places, so a wider vicinity would promise data that does not exist (ADR 0038).
 */
final class Vicinity
{
    /** @throws ConfigError if the vicinity reaches beyond the map area */
    public static function km(Config $config, GeneratedData $data): float
    {
        $mapKm = $data->borderMapKm();
        if ($mapKm > 0 && $config->vicinityKm > $mapKm) {
            throw new ConfigError(sprintf('vicinityKm: at most %s, the map area of the border zone (mapKm in contract/data/border-zone.json)', $mapKm));
        }

        return $config->vicinityKm;
    }
}
