<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AppaBz;

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
use CommonSight\Sdk\Station\DoseRateReadingMapper;
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Station\StationList;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Border zone, South Tyrol: gamma probes of the Agency for Environment, placed by data/stations.json. */
final class AppaBzFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'appa-bz',
            name: 'Umweltagentur Südtirol',
            attribution: new Attribution('Strahlung: Umweltagentur Südtirol (CC0)', 'https://dati.retecivica.bz.it/'),
            layer: 'radiation',
            scopes: [Scope::Border],
            schedule: new SourceSchedule(3600),
            expectations: SourceExpectations::measurements(4),
            order: 20,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $stations = new StationDirectory((new StationList())->read($environment->data->readOwnJson('stations.json')));

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new AppaBzRequest(),
            new AppaBzParser(new JsonBody(), new UtcTimeParser(), $stations),
            new DoseRateReadingMapper(),
        )));
    }
}
