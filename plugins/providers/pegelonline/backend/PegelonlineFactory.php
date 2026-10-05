<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline;

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

/** Water levels of the German federal waterways from PEGELONLINE (WSV), classified by the gauges' own states (B-10). */
final class PegelonlineFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: PegelonlineRequest::SOURCE_ID,
            name: 'PEGELONLINE',
            attribution: new Attribution('Pegel: WSV · PEGELONLINE', 'https://www.pegelonline.wsv.de/'),
            layer: 'water',
            scopes: [Scope::DE],
            schedule: new SourceSchedule(900),
            // About 650 gauges; fewer than 400 valid ones point to a format change (F-18).
            expectations: SourceExpectations::measurements(400),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new PegelonlineRequest(),
            new PegelonlineParser(new JsonBody()),
            new PegelonlineMapper(new GermanWaterAssessor(), new UtcTimeParser(), new \DateTimeZone('Europe/Berlin')),
        )));
    }
}
