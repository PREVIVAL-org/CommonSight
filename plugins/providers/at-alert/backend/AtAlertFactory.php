<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert;

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
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * The warnings of AT-Alert, the public warning system of Austria (cell broadcast of the interior ministry and the
 * warning centres of the states), as RTR publishes them on warnungen.at-alert.at. Read from the data call of that
 * website; there is no official interface.
 */
final class AtAlertFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: AtAlertRequest::SOURCE_ID,
            name: 'AT-Alert',
            attribution: new Attribution('Warnungen: AT-Alert (BMI, Landeswarnzentralen), veröffentlicht von der RTR', 'https://warnungen.at-alert.at/'),
            layer: 'warnings',
            scopes: [Scope::AT],
            // The website itself updates every 30 seconds; every minute is enough for the map.
            schedule: new SourceSchedule(60, lane: 'fast'),
            expectations: SourceExpectations::events(),
            order: 5,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new AtAlertRequest(),
            new AtAlertParser(new JsonBody(), new UtcTimeParser(), new TextCleaner(), new GeometryRounding(4)),
            new AtAlertMapper(new WarningSections()),
        )));
    }
}
