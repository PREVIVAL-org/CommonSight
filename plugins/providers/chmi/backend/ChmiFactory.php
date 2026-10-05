<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Chmi;

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
use CommonSight\Sdk\Text\UtcTimeParser;

/** Border zone, Czech Republic: ČHMÚ gauges with flood stages (SPA) from data/stations.json. */
final class ChmiFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'chmi',
            name: 'ČHMÚ',
            attribution: new Attribution('Pegel: ČHMÚ (CC BY 4.0)', 'https://opendata.chmi.cz/'),
            layer: 'water',
            scopes: [Scope::Border],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(300),
            order: 30,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $stations = new StationDirectory((new StationList())->read($environment->data->readOwnJson('stations.json')));

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new ChmiRequest($stations),
            new ChmiParser(new JsonBody(), new UtcTimeParser(), $stations),
            new WaterReadingMapper(new ChmiStageAssessor()),
        )));
    }
}
