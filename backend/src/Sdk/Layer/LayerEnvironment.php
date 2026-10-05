<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Port\PluginData;

/** What the core lends a layer when it is created: its data folder and the master data, its settings, the mechanics. */
final readonly class LayerEnvironment
{
    public function __construct(
        public PluginData $data,
        public LayerSettings $settings,
        public LayerMechanics $mechanics,
    ) {}
}
