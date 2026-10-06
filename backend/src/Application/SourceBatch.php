<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\Clock;

/**
 * Runs sources one after another while their time lasts, then reassembles every layer that got a new outcome, from the
 * latest outcomes of all its sources (concept: sources as plugins, 6.1). Used by the lanes, the fallback and by hand.
 */
final class SourceBatch
{
    /** Time a source needs at least: the HTTP client refuses requests with less than 2 s left (CurlHttpClient). */
    private const MIN_RESERVE_MS = 3_000;

    public function __construct(
        private readonly SourceRefresh $refresh,
        private readonly LayerPublisher $publisher,
        private readonly SourcePlans $plans,
        private readonly HealthRecorder $health,
        private readonly Clock $clock,
    ) {}

    /**
     * @param list<SourcePlan> $plans in the order in which they run
     * @param \Closure(SourcePlan): UtcInstant $deadline until when the source of a plan may still start
     * @param ?string $process the lane of a cron run or `fallback-<layer>`, for the self-healing marker; null by hand
     * @param ?DueSources $due runs a source only if it is still due when its turn comes (lanes, fallback); null by hand
     */
    public function run(array $plans, \Closure $deadline, string $trigger, ?string $process = null, ?DueSources $due = null): LaneReport
    {
        $touched = [];
        $skipped = 0;
        foreach ($plans as $plan) {
            $now = $this->clock->now();
            if (!$now->plusSeconds($this->reserveSec($plan))->isBefore($deadline($plan))) {
                $skipped++; // it would run out of time and lose its last good outcome; it stays due for the next run
                continue;
            }
            if ($this->refresh->refresh($plan, $now, $process, $due) !== null) {
                $touched[$plan->target()->name()] = $plan->target();
            }
        }

        return new LaneReport(false, $this->publish($touched, $trigger), $skipped);
    }

    /** The time the source typically needs, so that it does not start shortly before the deadline (D8). */
    private function reserveSec(SourcePlan $plan): int
    {
        $health = $this->health->read($plan);
        $typicalMs = $health === null ? 0 : ($health->typicalDurationMs ?? 0);

        return (int) ceil(max($typicalMs, self::MIN_RESERVE_MS) / 1000);
    }

    /** Before a run of a lane or a fallback: a source its last run left unfinished counts as failed. */
    public function heal(string $process): void
    {
        $this->refresh->heal($process, $this->clock->now());
    }

    /**
     * Reassembles the layers that no source will ever trigger (no sources in their scope), e.g. "setup". Every run, not
     * only once: a snapshot from an earlier release must not outlive an update; unchanged content writes no new file.
     *
     * @return array<string, RunOutcome>
     */
    public function publishLayersWithoutSources(string $trigger): array
    {
        $targets = [];
        foreach ($this->plans->targetsWithoutSources() as $target) {
            $targets[$target->name()] = $target;
        }

        return $this->publish($targets, $trigger);
    }

    /** Assembles a layer from the latest outcomes of its sources, without running any of them. */
    public function publishLayer(LayerTarget $target, string $trigger): RunOutcome
    {
        return $this->publisher->publish($target, $this->plans->of($target), $this->clock->now(), $trigger);
    }

    /**
     * @param array<string, LayerTarget> $targets
     * @return array<string, RunOutcome>
     */
    private function publish(array $targets, string $trigger): array
    {
        return array_map(fn(LayerTarget $target): RunOutcome => $this->publishLayer($target, $trigger), $targets);
    }
}
