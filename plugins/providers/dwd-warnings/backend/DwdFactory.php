<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Geo\GeometryRounding;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\HttpBudget;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\GeoJsonGeometry;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Source\WarningsWithoutGeometry;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Weather warnings of the Deutscher Wetterdienst from its WFS per district, in pages (Q-W-DE-*). */
final class DwdFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'dwd-warnings',
            name: 'DWD',
            attribution: new Attribution('Warnungen: Deutscher Wetterdienst (DWD)', 'https://www.dwd.de/'),
            layer: 'warnings',
            scopes: [Scope::DE],
            schedule: new SourceSchedule(60, lane: 'fast'),
            expectations: SourceExpectations::events(),
            // Pages of the warning areas reach some MB during severe weather (F-08).
            http: new HttpBudget(maxBytes: 20_000_000),
            order: 20,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $request = new DwdWarningRequest();

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            $request,
            new DwdWarningParser(new JsonBody(), new UtcTimeParser(), new TextCleaner(), new GeoJsonGeometry()),
            new DwdWarningMapper(new SafeUrl(), new GeometryRounding(), new WarningSections()),
            paging: new DwdPaging($request),
            detectors: [new WarningsWithoutGeometry()],
        )));
    }
}
