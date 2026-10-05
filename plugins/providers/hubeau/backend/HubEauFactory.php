<?php

declare(strict_types=1);

namespace CommonSight\Plugin\HubEau;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\HttpBudget;
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
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Station\StationList;
use CommonSight\Sdk\Station\WaterReadingMapper;
use CommonSight\Sdk\Station\WithoutStages;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Border zone, France: real-time water levels of Hub'Eau (OFB/BRGM), joined with the station list of data/stations.json. */
final class HubEauFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'hubeau',
            name: 'Hub\'Eau',
            attribution: new Attribution('Pegel: Hub\'Eau (OFB/BRGM, Licence Ouverte Etalab)', 'https://hubeau.eaufrance.fr/page/api-hydrometrie'),
            layer: 'water',
            scopes: [Scope::Border],
            schedule: new SourceSchedule(300),
            expectations: SourceExpectations::measurements(700),
            // The station query of the border zone is large and slow (up to about 30 s).
            http: new HttpBudget(timeoutSec: 40),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $stations = new StationDirectory((new StationList())->read($environment->data->readOwnJson('stations.json')));

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new HubEauRequest($stations),
            new HubEauParser(new JsonBody(), new UtcTimeParser(), $stations),
            new WaterReadingMapper(new WithoutStages()),
        )));
    }
}
