<?php

declare(strict_types=1);

namespace CommonSight\Plugin\PollenLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Current model pollen concentrations (CAMS) at selected places, in the countries and the border zone. */
final class PollenLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'pollen',
            global: false,
            border: true,
            color: '#c2507e',
            icon: 'flower-2',
            onMap: true,
            regionFilter: true,
            view: 'measurements',
            defaultActive: false,
            order: 35,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new PollenLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
