<?php

declare(strict_types=1);

namespace CommonSight\Domain\Schedule;

use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\UtcInstant;

/**
 * Staleness of layers (A-02, Architecture 6.3; concept: sources as plugins, V1): a layer is stale when one of its active
 * sources has not succeeded for staleFactor × its interval; sources in backoff count. The assembly stores that time in
 * the layer state (staleAfter); a layer without any assembly is stale. The most stale layer is reloaded first.
 */
final class StalenessPolicy
{
    public function __construct(private readonly float $staleFactor = 2.5) {}

    public function isStale(?LayerState $state, UtcInstant $now): bool
    {
        return $this->overdueSec($state, $now) > 0;
    }

    /**
     * The most stale layer, otherwise null.
     *
     * @param list<array{LayerTarget, ?LayerState}> $candidates
     */
    public function mostStale(array $candidates, UtcInstant $now): ?LayerTarget
    {
        return $this->staleFirst($candidates, $now)[0] ?? null;
    }

    /**
     * The stale layers, most stale first.
     *
     * @param list<array{LayerTarget, ?LayerState}> $candidates
     * @return list<LayerTarget>
     */
    public function staleFirst(array $candidates, UtcInstant $now): array
    {
        $stale = [];
        foreach ($candidates as $index => [$target, $state]) {
            $overdue = $this->overdueSec($state, $now);
            if ($overdue > 0) {
                $stale[] = [$target, $overdue, $index];
            }
        }
        usort($stale, static fn(array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        return array_map(static fn(array $entry): LayerTarget => $entry[0], $stale);
    }

    /**
     * From when a layer is stale, given the health and interval of each active source: the earliest time at which one of
     * them has gone without success for staleFactor × its interval. A source that never succeeded makes it stale at once.
     *
     * @param list<array{?SourceHealth, int}> $sources health and interval of each active source
     */
    public function staleAfter(array $sources, UtcInstant $now): ?UtcInstant
    {
        $earliest = null;
        foreach ($sources as [$health, $intervalSec]) {
            $lastSuccess = $health?->lastSuccessAt;
            $at = $lastSuccess === null ? $now : $lastSuccess->plusSeconds((int) ceil($this->staleFactor * $intervalSec));
            $earliest = $earliest === null || $at->isBefore($earliest) ? $at : $earliest;
        }

        return $earliest;
    }

    /** Seconds the layer is past its staleness; infinite without any assembly, 0 when not stale or never stale. */
    private function overdueSec(?LayerState $state, UtcInstant $now): float
    {
        if ($state?->checkedAt === null) {
            return INF;
        }
        if ($state->staleAfter === null) {
            return 0.0;
        }

        return (float) max(0, $now->secondsSince($state->staleAfter));
    }
}
