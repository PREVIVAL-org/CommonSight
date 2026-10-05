<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs;

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
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Earthquakes from the FDSN event service of the USGS, by the bounds of each country and the border zone. */
final class UsgsFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'usgs',
            name: 'USGS',
            attribution: new Attribution('Erdbeben: U.S. Geological Survey (USGS)', 'https://earthquake.usgs.gov/'),
            layer: 'nature',
            scopes: [Scope::DE, Scope::AT, Scope::CH, Scope::Border],
            schedule: new SourceSchedule(60),
            expectations: SourceExpectations::events(),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new UsgsRequest($environment->data->countries()),
            new UsgsParser(new JsonBody(), new UtcTimeParser(), new TextCleaner()),
            new UsgsMapper(new SafeUrl(), new GermanPlace()),
        )));
    }
}
