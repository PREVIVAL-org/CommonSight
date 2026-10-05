<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Umweltbundesamt;

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

/**
 * Probes of the Austrian radiation early warning system (BMLUK, Umweltbundesamt); the API has no positions, they come
 * from data/stations.json (tools/stations/build.mjs, ADR 0038).
 */
final class UmweltbundesamtFactory implements SourcePluginFactory
{
    /** Info page of the probes: the radiation early warning system of the BMLUK (Appendix A). */
    private const INFO_URL = 'https://mb.strahlenschutz.gv.at/';

    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: UmweltbundesamtRequest::SOURCE_ID,
            name: 'Strahlenfrühwarnsystem',
            attribution: new Attribution('Strahlung: Strahlenfrühwarnsystem, BMLUK / Umweltbundesamt', 'https://mb.strahlenschutz.gv.at/'),
            layer: 'radiation',
            scopes: [Scope::AT],
            schedule: new SourceSchedule(3600),
            expectations: SourceExpectations::measurements(90),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $stations = new StationDirectory((new StationList())->read($environment->data->readOwnJson('stations.json')));

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new UmweltbundesamtRequest(),
            new UmweltbundesamtParser(new JsonBody(), new UtcTimeParser(), $stations, self::INFO_URL),
            new DoseRateReadingMapper(),
        )));
    }
}
