<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Autobahn;

use CommonSight\Model\Msg;
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
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Traffic warnings, closures and short-term roadworks on German motorways from the Autobahn GmbH, three requests per motorway. */
final class AutobahnFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'autobahn',
            name: 'Autobahn GmbH',
            attribution: new Attribution('Verkehr: Autobahn GmbH des Bundes', 'https://www.autobahn.de/'),
            layer: 'traffic',
            scopes: [Scope::DE],
            schedule: new SourceSchedule(60),
            expectations: SourceExpectations::events(),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new AutobahnRequest(),
            new AutobahnParser(new JsonBody(), new TextCleaner()),
            new AutobahnMapper(new UtcTimeParser()),
            coverage: static fn(Scope $scope): Msg => new Msg('source.autobahn.coverage', ['roads' => implode(', ', AutobahnRequest::ROADS)]),
        )));
    }
}
