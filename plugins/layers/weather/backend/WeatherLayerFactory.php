<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WeatherLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Current model weather at selected places, in the countries and the border zone (Q-WE-*). */
final class WeatherLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'weather',
            global: false,
            border: true,
            color: '#388bbb',
            icon: 'cloud-sun',
            onMap: true,
            regionFilter: true,
            view: 'measurements',
            defaultActive: true,
            order: 20,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new WeatherLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
