<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Oeamtc;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\StableId;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Traffic notices in Austria from the GeoRSS feed of the ÖAMTC (Q-TR-AT-*). */
final class OeamtcFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'oeamtc',
            name: 'ÖAMTC',
            attribution: new Attribution('Verkehr: ÖAMTC', 'https://www.oeamtc.at/verkehrsservice/'),
            layer: 'traffic',
            scopes: [Scope::AT],
            schedule: new SourceSchedule(120),
            expectations: SourceExpectations::events(),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new OeamtcRequest(),
            new OeamtcParser($environment->xml, new UtcTimeParser(), new TextCleaner(), new StableId()),
            new OeamtcMapper(new SafeUrl()),
        )));
    }
}
