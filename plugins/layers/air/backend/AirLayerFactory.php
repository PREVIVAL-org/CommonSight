<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AirLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Current model air quality (CAMS) at selected places, in the countries and the border zone (Q-AI-*). */
final class AirLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'air',
            global: false,
            border: true,
            color: '#348f7b',
            icon: 'wind',
            onMap: true,
            regionFilter: true,
            view: 'measurements',
            defaultActive: false,
            order: 30,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new AirLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
