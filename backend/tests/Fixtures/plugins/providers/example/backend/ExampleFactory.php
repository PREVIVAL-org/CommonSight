<?php

declare(strict_types=1);

namespace CommonSight\Tests\Fixtures\Plugins\Example;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\SourceExpectations;

/** Describes the example source and creates its plugin. */
final class ExampleFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'example',
            name: 'Beispielwetter',
            attribution: new Attribution('Daten: Beispielwetter', 'https://api.example.org/'),
            layer: 'weather',
            scopes: [Scope::AT],
            schedule: new SourceSchedule(3600),
            expectations: SourceExpectations::measurements(),
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new ExamplePlugin($environment->http);
    }
}
