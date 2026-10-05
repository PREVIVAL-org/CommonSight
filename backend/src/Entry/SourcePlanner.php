<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Config\SourceSettings;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Model\Layer\LayerTarget;

/**
 * Makes the plans of the sources (concept: sources as plugins, 6.1): lane and interval from the description, overridable
 * in config.php (sources.<id>.lane, sources.<id>.intervalSec). The lane must exist, and no interval may be shorter than
 * the update rate or the minimum of the terms of use (F-19); both are configuration errors.
 */
final class SourcePlanner
{
    public function __construct(private readonly Config $config) {}

    /**
     * @param list<RegisteredSource> $sources
     * @param list<LayerTarget> $targets every layer in every scope
     */
    public function plans(array $sources, array $targets): SourcePlans
    {
        $plans = [];
        foreach ($sources as $source) {
            $description = $source->description;
            $settings = $this->config->sources[$description->id] ?? new SourceSettings();
            $lane = $settings->lane ?? $description->schedule->lane;
            if (!$this->config->lanes->has($lane)) {
                throw new ConfigError(sprintf('Source %s: unknown lane %s (lanes: %s)', $description->id, $lane, implode(', ', $this->config->lanes->names())));
            }
            $interval = $this->interval($source, $settings);
            foreach ($description->scopes as $scope) {
                $plans[] = new SourcePlan($source, $scope, $lane, $interval);
            }
        }

        return new SourcePlans($plans, $targets);
    }

    private function interval(RegisteredSource $source, SourceSettings $settings): int
    {
        $schedule = $source->description->schedule;
        if ($settings->intervalSec === null) {
            return $schedule->effectiveIntervalSec();
        }
        $minimum = max($schedule->updateRateSec, $schedule->termsMinIntervalSec ?? 0);
        if ($settings->intervalSec < $minimum) {
            throw new ConfigError(sprintf('sources.%s.intervalSec: at least %d s (update rate and terms of use of the source)', $source->description->id, $minimum));
        }

        return $settings->intervalSec;
    }
}
