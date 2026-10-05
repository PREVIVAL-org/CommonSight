<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Paa;

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
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Border zone, Poland: dose rate of the monitoring network of the Państwowa Agencja Atomistyki. */
final class PaaFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'paa',
            name: 'PAA',
            attribution: new Attribution('Strahlung: Państwowa Agencja Atomistyki (CC BY 3.0 PL)', 'https://monitoring.paa.gov.pl/'),
            layer: 'radiation',
            scopes: [Scope::Border],
            schedule: new SourceSchedule(3600),
            expectations: SourceExpectations::measurements(40),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new PaaRequest(),
            new PaaParser(new JsonBody(), new NumberParser(), new UtcTimeParser(), new \DateTimeZone('Europe/Warsaw')),
            new DoseRateReadingMapper(),
        )));
    }
}
