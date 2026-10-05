<?php

declare(strict_types=1);

namespace CommonSight\Plugin\MeteoAlarm;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\DetailEnrichment;
use CommonSight\Sdk\Source\DetailPlan;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Source\WarningsWithoutGeometry;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\StableId;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Weather warnings for Switzerland (MeteoSchweiz) from the Atom/CAP feed of MeteoAlarm (Q-W-CH-*), with the German texts
 * of each warning from its full CAP message (the feed names them in English or in the language of the region).
 */
final class MeteoAlarmFactory implements SourcePluginFactory
{
    /** German texts per run; the rest follows in the next runs. */
    private const DETAILS_PER_RUN = 30;

    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: MeteoAlarmRequest::SOURCE_ID,
            name: 'MeteoAlarm',
            attribution: new Attribution('Warnungen: MeteoSchweiz via MeteoAlarm', 'https://meteoalarm.org/'),
            layer: 'warnings',
            scopes: [Scope::CH],
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
                new MeteoAlarmRequest(),
                new MeteoAlarmParser($environment->xml, new UtcTimeParser(), new TextCleaner(), new StableId()),
                new MeteoAlarmMapper(new SafeUrl(), new WarningSections()),
                detail: new GermanCapDetail($environment->xml, new TextCleaner()),
                detectors: [new WarningsWithoutGeometry()],
            ),
            new DetailEnrichment($environment->http, $environment->store, new DetailPlan(self::DETAILS_PER_RUN)),
        ));
    }
}
