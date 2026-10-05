<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas;

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

/** Water levels and discharges of the Swiss Federal Office for the Environment via LINDAS (SPARQL), with danger levels (B-12). */
final class LindasFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'bafu-lindas',
            name: 'BAFU/LINDAS',
            attribution: new Attribution('Pegel: Bundesamt für Umwelt BAFU · LINDAS', 'https://www.hydrodaten.admin.ch/'),
            layer: 'water',
            scopes: [Scope::CH],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(150),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new LindasRequest(),
            new LindasParser(new JsonBody()),
            new LindasMapper(new SwissWaterAssessor(), new UtcTimeParser()),
        )));
    }
}
