<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Geo\AustriaLambertGeometry;
use CommonSight\Sdk\Geo\AustriaLambertProjection;
use CommonSight\Sdk\Geo\InteriorPoint;
use CommonSight\Sdk\Geo\ScanlineIntervals;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\DetailEnrichment;
use CommonSight\Sdk\Source\DetailPlan;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Source\WarningsWithoutGeometry;
use CommonSight\Sdk\Source\WarningsWithoutText;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Weather warnings of GeoSphere Austria per municipality; the detail text of each warning is fetched for a point inside
 * its area and kept in the store of the plugin until the warning ends (Q-W-AT-*).
 */
final class GeoSphereFactory implements SourcePluginFactory
{
    /** Detail requests per run; the rest follows in the next runs. */
    private const DETAILS_PER_RUN = 30;
    /** The Austrian states by the first digit of the municipality code (GKZ). */
    private const STATES = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: GeoSphereStatusRequest::SOURCE_ID,
            name: 'GeoSphere Austria',
            attribution: new Attribution('Warnungen: GeoSphere Austria, CC BY 4.0; Gebietsgrundlage: Statistik Austria', 'https://warnungen.zamg.at/'),
            layer: 'warnings',
            scopes: [Scope::AT],
            schedule: new SourceSchedule(60, lane: 'fast'),
            expectations: SourceExpectations::events(rejectedMeansPartial: true),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $codes = $environment->data->regionCodes();
        $stateNames = [];
        foreach (self::STATES as $digit) {
            $stateNames[$digit] = $codes->nameOf(RegionId::austrianState($digit)) ?? '';
        }
        $geometry = new AustriaLambertGeometry(new AustriaLambertProjection());
        $detail = new GeoSphereDetailSource(
            $geometry,
            new InteriorPoint(new ScanlineIntervals()),
            new GeoSphereDetailParser(new JsonBody(), new TextCleaner()),
            new GeoSphereDetailMatcher(),
            new GeoSphereDetailComposer(new WarningSections(), new UtcTimeParser()),
        );

        return new StandardSourcePlugin(new RequestParseMap(
            $environment->http,
            new SourceParts(
                new GeoSphereStatusRequest(),
                new GeoSphereStatusParser(new JsonBody()),
                new GeoSphereWarningMapper($geometry, new GeoSphereWarnType(), $stateNames),
                detail: $detail,
                detectors: [new WarningsWithoutGeometry(), new WarningsWithoutText()],
            ),
            new DetailEnrichment($environment->http, $environment->store, new DetailPlan(self::DETAILS_PER_RUN)),
        ));
    }
}
