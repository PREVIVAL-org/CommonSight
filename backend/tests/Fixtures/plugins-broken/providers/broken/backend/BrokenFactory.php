<?php

declare(strict_types=1);

namespace CommonSight\Tests\Fixtures\Plugins\Broken;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\SourceExpectations;

/** Describes a source for AT and CH, but has a recording only for AT and no profile. */
final class BrokenFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'broken',
            name: 'Kaputt',
            attribution: new Attribution('Kaputt', 'https://api.example.org/'),
            layer: 'weather',
            scopes: [Scope::AT, Scope::CH],
            schedule: new SourceSchedule(3600),
            expectations: SourceExpectations::measurements(),
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new BrokenPlugin();
    }
}
