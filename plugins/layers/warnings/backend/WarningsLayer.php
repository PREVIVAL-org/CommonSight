<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\EmptyStatsBuilder;
use CommonSight\Sdk\Layer\ExpiredItemFilter;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerMechanics;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerSources;
use CommonSight\Sdk\Layer\SeveritySorter;

/** Official warnings: weather warnings and civil protection messages per country, most severe first (Q-W-*). Display name and link per scope from data/names.json. */
final class WarningsLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = [new ExpiredItemFilter(), new ItemDeduplicator(), $this->mechanics->regions($scope), new SeveritySorter()];

        return new LayerDefinition(LayerId::from('warnings'), $scope, $name, $url, new Msg('layer.warnings.note.' . $scope->value), $steps, new EmptyStatsBuilder());
    }
}
