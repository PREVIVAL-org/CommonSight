<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WaterLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;

/** Water levels and discharges of the gauges, classified by the stages of their services; outdated after 6 hours (Q-WA-*, B-10 to B-12). */
final class WaterLayerFactory implements LayerPluginFactory
{
    public const MAX_AGE_HOURS = 6;

    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'water',
            global: false,
            border: true,
            color: '#398ed4',
            icon: 'waves-horizontal',
            onMap: true,
            regionFilter: true,
            view: 'measurements',
            defaultActive: true,
            order: 40,
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new WaterLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
