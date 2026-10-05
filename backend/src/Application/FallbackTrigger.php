<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\Lock;
use CommonSight\Port\LockFactory;
use CommonSight\Port\TriggerStamp;

/**
 * After the status response has been sent, refreshes the due sources of the most stale layer that has any, at most once
 * per minimum gap per layer, each within the fallback budget of its lane; sources of lanes without fallback (slow) and
 * sources in backoff are left out, so that a layer whose source keeps failing does not block the others (A-02,
 * Architecture 6.3; concept: sources as plugins, 6.1).
 */
final class FallbackTrigger
{
    /** @param array<string, int|null> $fallbackSec fallback budget per lane; null = the lane has no fallback */
    public function __construct(
        private readonly StalenessPolicy $staleness,
        private readonly TriggerStamp $stamps,
        private readonly LockFactory $locks,
        private readonly SourcePlans $plans,
        private readonly DueSources $due,
        private readonly SourceBatch $batch,
        private readonly array $fallbackSec,
        private readonly int $minIntervalSec,
    ) {}

    /** @param list<array{LayerTarget, ?LayerState}> $candidates */
    public function trigger(array $candidates, UtcInstant $now): ?RunOutcome
    {
        foreach ($this->staleness->staleFirst($candidates, $now) as $target) {
            if ($this->duePlans($target, $now) === []) {
                continue;
            }
            // Another request just started this layer's fallback, or one is still running: the next stale layer gets
            // its turn. A request runs at most one fallback.
            if (!$this->stamps->claim('trigger-' . $target->name(), $now, $this->minIntervalSec)) {
                continue;
            }
            $lock = $this->locks->acquire('fallback-' . strtolower($target->name()));
            if ($lock === null) {
                continue;
            }

            return $this->runLocked($target, $now, $lock);
        }

        return null;
    }

    /**
     * One fallback per layer at a time (a run can outlast the minimum gap). Like a lane, it marks its running source: a
     * process that died in it (e.g. the memory limit of PHP-FPM) is recorded as a crash with backoff by the next
     * fallback of the layer, before the due sources are chosen again. Only under the lock, so that a running fallback
     * is never taken for a dead one.
     */
    private function runLocked(LayerTarget $target, UtcInstant $now, Lock $lock): ?RunOutcome
    {
        $process = 'fallback-' . strtolower($target->name());
        try {
            $this->batch->heal($process);
            $plans = $this->duePlans($target, $now);
            if ($plans === []) {
                return null;
            }
            $report = $this->batch->run($plans, fn(SourcePlan $plan): UtcInstant => $now->plusSeconds($this->fallbackSec[$plan->lane] ?? 0), 'fallback', $process, $this->due);

            return $report->outcomes[$target->name()] ?? null;
        } finally {
            $lock->release();
        }
    }

    /** @return list<SourcePlan> */
    private function duePlans(LayerTarget $target, UtcInstant $now): array
    {
        return array_values(array_filter(
            $this->due->among($this->plans->of($target), $now),
            fn(SourcePlan $plan): bool => ($this->fallbackSec[$plan->lane] ?? null) !== null,
        ));
    }
}
