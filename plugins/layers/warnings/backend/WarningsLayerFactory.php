<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Official warnings: weather warnings and civil protection messages per country, most severe first (Q-W-*). */
final class WarningsLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'warnings',
            global: false,
            border: false,
            color: '#dc8c2b',
            icon: 'triangle-alert',
            onMap: true,
            regionFilter: true,
            view: 'events',
            defaultActive: true,
            order: 10,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new WarningsLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
