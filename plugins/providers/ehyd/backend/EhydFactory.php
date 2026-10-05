<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd;

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
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Water levels and discharges of the Austrian hydrographic service (eHYD), classified by its own flood code (B-11). */
final class EhydFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'ehyd',
            name: 'eHYD',
            attribution: new Attribution('Pegel: Hydrographie Österreich (BML) · eHYD', 'https://ehyd.gv.at/'),
            layer: 'water',
            scopes: [Scope::AT],
            schedule: new SourceSchedule(900),
            expectations: SourceExpectations::measurements(150),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new EhydRequest(),
            new EhydParser(new JsonBody(), new NumberParser()),
            new EhydMapper(new AustrianWaterAssessor(), new UtcTimeParser(), new SafeUrl(), new \DateTimeZone('Europe/Vienna')),
        )));
    }
}
