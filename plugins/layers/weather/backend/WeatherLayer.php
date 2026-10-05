<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WeatherLayer;

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

/** Current model weather at selected places, in the countries and the border zone (Q-WE-*). Display name and link per scope from data/names.json. */
final class WeatherLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = $scope === Scope::Border
            ? [new ItemDeduplicator(), ...$this->mechanics->borderZone()]
            : [new ItemDeduplicator(), $this->mechanics->regions($scope)];

        return new LayerDefinition(LayerId::from('weather'), $scope, $name, $url, new Msg('layer.weather.note'), $steps, new EmptyStatsBuilder());
    }
}
