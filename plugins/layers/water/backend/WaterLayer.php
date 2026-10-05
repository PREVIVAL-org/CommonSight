<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WaterLayer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\EmptyStatsBuilder;
use CommonSight\Sdk\Layer\FreshnessApplier;
use CommonSight\Sdk\Layer\FreshnessCheck;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerMechanics;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerSources;

/** Water levels and discharges of the gauges, classified by the stages of their services; outdated after 6 hours (Q-WA-*, B-10 to B-12). Display name and link per scope from data/names.json. */
final class WaterLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = $scope === Scope::Border
            ? [new ItemDeduplicator(), ...$this->mechanics->borderZone(), $this->freshness()]
            : [new ItemDeduplicator(), $this->freshness(), $this->mechanics->regions($scope)];

        return new LayerDefinition(LayerId::from('water'), $scope, $name, $url, new Msg('layer.water.note.' . $scope->value), $steps, new EmptyStatsBuilder());
    }

    private function freshness(): FreshnessApplier
    {
        return new FreshnessApplier(new FreshnessCheck(), WaterLayerFactory::MAX_AGE_HOURS);
    }
}
