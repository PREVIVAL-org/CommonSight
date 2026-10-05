<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NewsLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Current headlines of public broadcasters, filtered by topic (Q-NE-*). */
final class NewsLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'news',
            global: true,
            border: false,
            color: '#657488',
            icon: 'newspaper',
            onMap: false,
            regionFilter: false,
            view: null,
            defaultActive: false,
            order: 90,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new NewsLayer($environment->data->readOwnJson('names.json'));
    }
}
