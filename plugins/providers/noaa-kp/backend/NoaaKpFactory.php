<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaKp;

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
use CommonSight\Sdk\Text\UtcTimeParser;

/** The planetary Kp index of NOAA SWPC: latest value and course of the last days (Q-SP-*). */
final class NoaaKpFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: SwpcRequest::SOURCE_ID,
            name: 'NOAA Kp-Index',
            attribution: new Attribution('Weltraumwetter: NOAA SWPC', 'https://www.swpc.noaa.gov/'),
            layer: 'space',
            scopes: [Scope::Global],
            schedule: new SourceSchedule(300),
            expectations: SourceExpectations::measurements(),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new SwpcRequest(),
            new KpParser(new JsonBody(), new UtcTimeParser()),
            new KpMapper(),
        )));
    }
}
