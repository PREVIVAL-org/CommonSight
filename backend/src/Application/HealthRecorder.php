<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\BackoffPolicy;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Domain\Source\SourceState;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\RandomSource;
use CommonSight\Port\SourceHealthStore;

/**
 * Records the health of a source after each run: success clears failures and backoff; a failure sets the backoff
 * (1 min doubling to 30 min, Retry-After honoured); the duration feeds the typical run time (concept: sources as
 * plugins, 6, D8).
 */
final class HealthRecorder
{
    public function __construct(
        private readonly SourceHealthStore $store,
        private readonly BackoffPolicy $backoff,
        private readonly RandomSource $random,
        private readonly string $release,
    ) {}

    public function read(SourcePlan $plan): ?SourceHealth
    {
        return $this->store->read($plan->id(), $plan->scope);
    }

    public function record(SourcePlan $plan, SourceResult $result, UtcInstant $now, int $durationMs): void
    {
        $health = $this->read($plan) ?? new SourceHealth($plan->id(), $plan->scope);
        $this->store->write(match ($result->outcome) {
            SourceState::Succeeded => $health->succeeded($now, count($result->items), $durationMs, $this->release),
            SourceState::Disabled, SourceState::Unconfigured, SourceState::Pending => $health->disabled($now, $this->release),
            SourceState::Failed => $this->failed($health, (string) $result->failureReason, $now, $durationMs, $result->retryAfterSec),
        });
    }

    /**
     * A run of this source that never finished (the process died): counts as a failure with backoff, unless the source
     * has run again since it started. Its run time is unknown and leaves the typical one as it is.
     */
    public function recordCrash(string $sourceId, Scope $scope, ?UtcInstant $startedAt, UtcInstant $now): void
    {
        $health = $this->store->read($sourceId, $scope) ?? new SourceHealth($sourceId, $scope);
        if ($startedAt !== null && $health->lastAttemptAt?->isAfter($startedAt) === true) {
            return;
        }
        $this->store->write($this->failed($health, 'crashed: the process ended during the run', $now, null, null));
    }

    private function failed(SourceHealth $health, string $cause, UtcInstant $now, ?int $durationMs, ?int $retryAfterSec): SourceHealth
    {
        $until = $this->backoff->until($health->consecutiveFailures + 1, $now, $this->random->fraction(), $retryAfterSec);

        return $health->failed($now, $cause, $until, $durationMs, $this->release);
    }
}
