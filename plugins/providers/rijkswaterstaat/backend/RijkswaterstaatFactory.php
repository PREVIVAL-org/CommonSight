<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Rijkswaterstaat;

use CommonSight\Model\Value\Scope;
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
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Station\StationList;
use CommonSight\Sdk\Station\WaterReadingMapper;
use CommonSight\Sdk\Station\WithoutStages;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Border zone, Netherlands: water levels above NAP of Rijkswaterstaat for the stations of data/stations.json. */
final class RijkswaterstaatFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'rijkswaterstaat',
            name: 'Rijkswaterstaat',
            attribution: new Attribution('Pegel: Rijkswaterstaat (CC0)', 'https://rijkswaterstaatdata.nl/waterdata/'),
            layer: 'water',
            scopes: [Scope::Border],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(150),
            order: 20,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $stations = new StationDirectory((new StationList())->read($environment->data->readOwnJson('stations.json')));

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new RijkswaterstaatRequest($stations),
            new RijkswaterstaatParser(new JsonBody(), new UtcTimeParser()),
            new WaterReadingMapper(new WithoutStages()),
        )));
    }
}
