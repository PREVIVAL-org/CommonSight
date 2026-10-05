<?php

declare(strict_types=1);

namespace CommonSight\Plugin\TrafficLayer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\EmptyStatsBuilder;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerMechanics;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerSources;

/** Traffic notices of the road operators and automobile clubs; Switzerland waits for a data access (Q-TR-*). Display name and link per scope from data/names.json. */
final class TrafficLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = $sources->sources === [] ? [] : [new ItemDeduplicator(), $this->mechanics->regions($scope)];

        return new LayerDefinition(LayerId::from('traffic'), $scope, $name, $url, new Msg('layer.traffic.note.' . $scope->value), $steps, new EmptyStatsBuilder());
    }
}
