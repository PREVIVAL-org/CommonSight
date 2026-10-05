<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\DueSourcePolicy;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Model\Value\UtcInstant;

/** Picks the due sources by their health, most overdue first (concept: sources as plugins, 6.1, D8). */
final class DueSources
{
    public function __construct(
        private readonly HealthRecorder $health,
        private readonly DueSourcePolicy $policy,
        private readonly string $release,
    ) {}

    /**
     * @param list<SourcePlan> $plans
     * @return list<SourcePlan>
     */
    public function among(array $plans, UtcInstant $now): array
    {
        return $this->policy->dueFirst($plans, $this->health->read(...), $now, $this->release);
    }

    /** Whether the source of a plan is (still) due, e.g. after waiting for its lock. */
    public function isDue(SourcePlan $plan, UtcInstant $now): bool
    {
        return $this->among([$plan], $now) !== [];
    }
}
