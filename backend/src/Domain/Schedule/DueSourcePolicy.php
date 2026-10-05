<?php

declare(strict_types=1);

namespace CommonSight\Domain\Schedule;

use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\UtcInstant;

/**
 * Decides which sources of a lane are due and in which order they run (concept: sources as plugins, 6.1, D8): due when
 * its interval has passed since the last attempt, after a failure as soon as its backoff has ended, and when there is no
 * outcome of this release yet; never during a backoff, and never before the minimum interval of the provider's terms
 * has passed since the last attempt, whatever the reason (a failure, a new release). The most overdue first, so that
 * none starves.
 */
final class DueSourcePolicy
{
    /** @param int|null $termsMinSec minimum interval of the provider's terms of use, if it names one */
    public function isDue(?SourceHealth $health, int $intervalSec, UtcInstant $now, string $release, ?int $termsMinSec = null): bool
    {
        if ($health === null) {
            return true;
        }
        if ($health->inBackoff($now) || $this->withinTerms($health, $termsMinSec, $now)) {
            return false;
        }

        return $health->outcomeRelease !== $release || $health->consecutiveFailures > 0 || $this->overdueRatio($health, $intervalSec, $now) >= 1.0;
    }

    private function withinTerms(SourceHealth $health, ?int $termsMinSec, UtcInstant $now): bool
    {
        return $termsMinSec !== null && $health->lastAttemptAt !== null && $now->secondsSince($health->lastAttemptAt) < $termsMinSec;
    }

    /** Time since the last attempt in multiples of the interval; infinite without any attempt. */
    public function overdueRatio(?SourceHealth $health, int $intervalSec, UtcInstant $now): float
    {
        if ($health?->lastAttemptAt === null) {
            return INF;
        }

        return $now->secondsSince($health->lastAttemptAt) / $intervalSec;
    }

    /**
     * The due plans, most overdue first.
     *
     * @param list<SourcePlan> $plans
     * @param \Closure(SourcePlan): ?SourceHealth $health
     * @return list<SourcePlan>
     */
    public function dueFirst(array $plans, \Closure $health, UtcInstant $now, string $release): array
    {
        $due = [];
        foreach ($plans as $plan) {
            $state = $health($plan);
            if ($this->isDue($state, $plan->intervalSec, $now, $release, $plan->source->description->schedule->termsMinIntervalSec)) {
                $due[] = [$plan, $state?->outcomeRelease !== $release ? INF : $this->overdueRatio($state, $plan->intervalSec, $now)];
            }
        }
        usort($due, static fn(array $a, array $b): int => [$b[1], $a[0]->key()] <=> [$a[1], $b[0]->key()]);

        return array_map(static fn(array $entry): SourcePlan => $entry[0], $due);
    }
}
