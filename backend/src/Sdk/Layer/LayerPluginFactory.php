<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

/**
 * Entry point of a layer package (concept: layers as plugins): its plugin.php returns the factory. The core asks it for
 * the description at build time and creates the layer when it is assembled.
 */
interface LayerPluginFactory
{
    public function describe(): LayerDescription;

    public function create(LayerEnvironment $environment): LayerPlugin;
}
