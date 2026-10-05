<?php

declare(strict_types=1);

namespace CommonSight\Plugin\TrafficLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Traffic notices of the road operators and automobile clubs; Switzerland waits for a data access (Q-TR-*). */
final class TrafficLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'traffic',
            global: false,
            border: false,
            color: '#768d9f',
            icon: 'route',
            onMap: true,
            regionFilter: true,
            view: 'events',
            defaultActive: false,
            order: 80,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new TrafficLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
