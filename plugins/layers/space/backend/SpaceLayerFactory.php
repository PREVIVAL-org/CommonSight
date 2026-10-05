<?php

declare(strict_types=1);

namespace CommonSight\Plugin\SpaceLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Global space weather: planetary Kp index and NOAA scales G, R, S (Q-SP-*). */
final class SpaceLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'space',
            global: true,
            border: false,
            color: '#a37dc2',
            icon: 'satellite',
            onMap: false,
            regionFilter: false,
            view: 'measurements',
            defaultActive: false,
            order: 60,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new SpaceLayer($environment->data->readOwnJson('names.json'));
    }
}
