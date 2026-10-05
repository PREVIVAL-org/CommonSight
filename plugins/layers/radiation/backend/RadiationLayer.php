<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\FreshnessApplier;
use CommonSight\Sdk\Layer\FreshnessCheck;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerMechanics;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerSources;

/** Ambient dose rate of the gamma probes, classified by display thresholds of this layer (settings warningUSvH, highUSvH); outdated after 12 hours (Q-RA-*, B-13). Display name and link per scope from data/names.json. */
final class RadiationLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
        private readonly RadiationThresholds $thresholds,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = $scope === Scope::Border
            ? [new DoseRateAssessmentApplier(new RadiationAssessor($this->thresholds)), new ItemDeduplicator(), ...$this->mechanics->borderZone(), $this->freshness()]
            : [new DoseRateAssessmentApplier(new RadiationAssessor($this->thresholds)), new ItemDeduplicator(), $this->mechanics->regions($scope), $this->freshness()];

        return new LayerDefinition(LayerId::from('radiation'), $scope, $name, $url, new Msg('layer.radiation.note', ['elevated' => $this->thresholds->elevated, 'high' => $this->thresholds->high]), $steps, new RadiationStatsBuilder($this->thresholds, RadiationLayerFactory::MAX_AGE_HOURS));
    }

    private function freshness(): FreshnessApplier
    {
        return new FreshnessApplier(new FreshnessCheck(), RadiationLayerFactory::MAX_AGE_HOURS);
    }
}
