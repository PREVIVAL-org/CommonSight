<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Geo\GeometryRounding;
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
use CommonSight\Sdk\Source\WarningsWithoutGeometry;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * The warnings NINA publishes besides those of the DWD: MoWaS (BBK), KATWARN, BIWAPP, police and the flood portals of
 * the states; the warning area of each message is a detail fetched one by one and kept in the store of the plugin
 * until the message expires (Q-W-DE-*).
 */
final class MowasFactory implements SourcePluginFactory
{
    /** Detail requests per run; the rest follows in the next runs. */
    private const DETAILS_PER_RUN = 30;

    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: MowasRequest::SOURCE_ID,
            name: 'BBK/NINA',
            attribution: new Attribution('Warnungen: BBK/NINA (MoWaS, KATWARN, BIWAPP, Polizei, Hochwasserportale)', 'https://warnung.bund.de/'),
            layer: 'warnings',
            scopes: [Scope::DE],
            schedule: new SourceSchedule(60, lane: 'fast'),
            expectations: SourceExpectations::events(),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap(
            $environment->http,
            new SourceParts(
                new MowasRequest(),
                new MowasParser(new JsonBody(), new UtcTimeParser(), new TextCleaner()),
                new MowasMapper(),
                detail: new MowasGeometryDetail(new JsonBody(), new GeometryRounding(4)),
                detectors: [new WarningsWithoutGeometry()],
            ),
            new DetailEnrichment($environment->http, $environment->store, new DetailPlan(self::DETAILS_PER_RUN)),
        ));
    }
}
