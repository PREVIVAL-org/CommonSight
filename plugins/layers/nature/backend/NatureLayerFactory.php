<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NatureLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Earthquakes of the last seven days in and around the countries, newest first (Q-NA-*). */
final class NatureLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'nature',
            global: false,
            border: true,
            color: '#9476ce',
            icon: 'activity',
            onMap: true,
            regionFilter: true,
            view: 'events',
            defaultActive: false,
            order: 70,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new NatureLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
