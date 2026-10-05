<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Domain\Source\SourceSelection;
use CommonSight\Model\Value\UtcInstant;

/**
 * What the status of a layer says about its rhythm, from its active sources (concept: sources as plugins, V1): the
 * shortest interval, and from when the layer is stale. Switched-off sources and sources without their secrets (not set up)
 * do not count: they never succeed, so they would keep the layer stale for good.
 */
final class LayerSchedule
{
    /** @param array<string, int|null> $laneCadences how often each lane runs, in seconds (null = not known) */
    public function __construct(
        private readonly HealthRecorder $health,
        private readonly StalenessPolicy $staleness,
        private readonly SourceSelection $selection,
        private readonly array $laneCadences = [],
    ) {}

    /**
     * @param list<SourcePlan> $plans
     * @return array{?int, ?UtcInstant} shortest interval and stale-after time; nulls without active sources
     */
    public function of(array $plans, UtcInstant $now): array
    {
        $active = array_values(array_filter($plans, fn(SourcePlan $p): bool => $this->selection->isEnabled($p->id()) && $p->source->missingSecrets === []));
        if ($active === []) {
            return [null, null];
        }
        // Stale only when a source missed its run: not before its lane came around (a 60 s source in a lane every 5 min).
        $sources = array_map(fn(SourcePlan $p): array => [$this->health->read($p), max($p->intervalSec, $this->laneCadences[$p->lane] ?? 0)], $active);

        return [min(array_map(static fn(SourcePlan $p): int => $p->intervalSec, $active)), $this->staleness->staleAfter($sources, $now)];
    }
}
