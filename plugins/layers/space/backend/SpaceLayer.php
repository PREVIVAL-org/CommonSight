<?php

declare(strict_types=1);

namespace CommonSight\Plugin\SpaceLayer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerSources;

/** Global space weather: planetary Kp index and NOAA scales G, R, S (Q-SP-*). Display name and link per scope from data/names.json. */
final class SpaceLayer implements LayerPlugin
{
    public function __construct(
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = [new ItemDeduplicator()];

        return new LayerDefinition(LayerId::from('space'), $scope, $name, $url, new Msg('layer.space.note'), $steps, new SpaceStatsBuilder());
    }
}
