<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;
use CommonSight\Sdk\Layer\LayerSetting;

/** Ambient dose rate of the gamma probes, classified by display thresholds of this layer (settings warningUSvH, highUSvH); outdated after 12 hours (Q-RA-*, B-13). */
final class RadiationLayerFactory implements LayerPluginFactory
{
    public const MAX_AGE_HOURS = 12;

    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'radiation',
            global: false,
            border: true,
            color: '#b0a235',
            icon: 'radiation',
            onMap: true,
            regionFilter: true,
            view: 'measurements',
            defaultActive: false,
            order: 50,
            settings: [
                new LayerSetting('warningUSvH', 0.3, 0.01, null, 'orange from this dose rate (µSv/h)'),
                new LayerSetting('highUSvH', 1.0, 0.01, null, 'red from this dose rate (µSv/h)'),
            ],
        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        $thresholds = new RadiationThresholds($environment->settings->float('warningUSvH'), $environment->settings->float('highUSvH'));

        return new RadiationLayer($environment->mechanics, $environment->data->readOwnJson('names.json'), $thresholds);
    }
}
