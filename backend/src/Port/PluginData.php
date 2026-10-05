<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Decoded;
use CommonSight\Model\Place\City;
use CommonSight\Model\Place\Country;
use CommonSight\Model\Place\RegionCodes;

/** Read access of a plugin to the master data of its own package (data/) and to the master data of the core. */
interface PluginData
{
    /**
     * A JSON file from the data/ folder of the plugin's own package.
     *
     * @throws \RuntimeException if the file is missing or not valid JSON
     */
    public function readOwnJson(string $file): Decoded;

    /** @return list<City> places of the core (contract/data/cities.json) */
    public function cities(): array;

    /** @return list<City> places of the core in the neighbouring countries, scope border (contract/data/border-places.json) */
    public function borderPlaces(): array;

    /** Maps stable official codes (ISO 3166-2, German, Austrian and Swiss state codes) to the regions of the core. */
    public function regionCodes(): RegionCodes;

    /** @return array<string, Country> the countries of the core with bounds and official links, by code */
    public function countries(): array;
}
