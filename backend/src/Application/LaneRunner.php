<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\Clock;
use CommonSight\Port\LockFactory;

/**
 * A cron run of a lane (concept: sources as plugins, 6.1): one process per lane (lock), first the self-healing check,
 * then the due sources of the lane, most overdue first, as long as the budget lasts; the layers they belong to are
 * reassembled afterwards. No source starts after the deadline.
 */
final class LaneRunner
{
    public function __construct(
        private readonly LockFactory $locks,
        private readonly SourcePlans $plans,
        private readonly DueSources $due,
        private readonly SourceBatch $batch,
        private readonly Clock $clock,
    ) {}

    public function run(string $lane, UtcInstant $deadline): LaneReport
    {
        $lock = $this->locks->acquire('lane-' . $lane);
        if ($lock === null) {
            return LaneReport::busy();
        }
        try {
            $this->batch->heal($lane);
            $due = $this->due->among($this->plans->inLane($lane), $this->clock->now());
            $report = $this->batch->run($due, static fn(SourcePlan $plan): UtcInstant => $deadline, 'cron', $lane, $this->due);

            return new LaneReport(false, $report->outcomes + $this->batch->publishLayersWithoutSources(), $report->skippedForBudget);
        } finally {
            $lock->release();
        }
    }
}
