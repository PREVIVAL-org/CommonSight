<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Alertswiss;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Geo\GeometryRounding;
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
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Civil protection alerts of Switzerland from Alertswiss (BABS): what the federal government and the cantons publish on
 * alert.swiss, e.g. fire bans, forest fire danger, landslides, events. Read from the JSON the website itself loads;
 * there is no official interface.
 */
final class AlertswissFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: AlertswissRequest::SOURCE_ID,
            name: 'Alertswiss (BABS)',
            attribution: new Attribution('Warnungen: Alertswiss, Bundesamt für Bevölkerungsschutz BABS', 'https://www.alert.swiss/'),
            layer: 'warnings',
            scopes: [Scope::CH],
            // The website itself; every two minutes is enough for alerts that mostly stay for days.
            schedule: new SourceSchedule(120, lane: 'fast'),
            expectations: SourceExpectations::events(),
            order: 20,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new AlertswissRequest(),
            new AlertswissParser(new JsonBody(), new UtcTimeParser(), new TextCleaner(), new AlertAreas(new GeometryRounding(4))),
            new AlertswissMapper(new SafeUrl(), new WarningSections()),
        )));
    }
}
