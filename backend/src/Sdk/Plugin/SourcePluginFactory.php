<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/**
 * Entry point of a plugin package, returned by its manifest plugin.php: describes the source without any service (so
 * that the build can check the description) and creates the plugin with its environment. Needs a constructor without
 * parameters, because the core creates it from the generated registry.
 */
interface SourcePluginFactory
{
    public function describe(): SourceDescription;

    public function create(PluginEnvironment $environment): SourcePlugin;
}
