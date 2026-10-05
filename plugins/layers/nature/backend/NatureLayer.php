<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NatureLayer;

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
use CommonSight\Sdk\Layer\NewestFirstSorter;

/** Earthquakes of the last seven days in and around the countries, newest first (Q-NA-*). Display name and link per scope from data/names.json. */
final class NatureLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = $scope === Scope::Border
            ? [new ItemDeduplicator(), ...$this->mechanics->borderZone(), new NewestFirstSorter()]
            : [new ItemDeduplicator(), $this->mechanics->regions($scope), new NewestFirstSorter()];

        return new LayerDefinition(LayerId::from('nature'), $scope, $name, $url, new Msg('layer.nature.note'), $steps, new EmptyStatsBuilder());
    }
}
