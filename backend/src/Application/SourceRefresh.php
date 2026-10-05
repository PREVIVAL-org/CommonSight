<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\LockFactory;
use CommonSight\Port\Logger;
use CommonSight\Port\RunMarker;

/**
 * Runs one source in one scope (concept: sources as plugins, 6.1, 6.2): lock per source and scope, isolated run,
 * outcome kept for the assembly, health recorded. In a lane run or a fallback the source is marked as running, so that
 * the next run of the lane or fallback of the layer notices a process that died in it.
 */
final class SourceRefresh
{
    public function __construct(
        private readonly LockFactory $locks,
        private readonly PluginRunner $runner,
        private readonly OutcomeArchive $outcomes,
        private readonly HealthRecorder $health,
        private readonly RunMarker $marker,
        private readonly Logger $log,
    ) {}

    /**
     * @param ?string $process the lane of a cron run or `fallback-<layer>`, for the self-healing marker; null by hand
     * @param ?DueSources $due checked again under the lock: the source was due when the run picked it, but a lane and a
     *     fallback pick independently, and the other may have run it meanwhile; null by hand (always runs)
     * @return SourceResult|null null when the source is being run by another process, is no longer due, or its
     *     bookkeeping failed before it ran
     */
    public function refresh(SourcePlan $plan, UtcInstant $now, ?string $process = null, ?DueSources $due = null): ?SourceResult
    {
        $lock = $this->locks->acquire('source-' . $plan->key());
        if ($lock === null) {
            return null;
        }
        if ($due !== null && !$due->isDue($plan, $now)) {
            $lock->release();

            return null;
        }
        $result = null;
        try {
            if ($process !== null) {
                $this->marker->start($process, $plan->key(), $now);
            }
            $started = hrtime(true);
            $result = $this->runner->run($plan->source, $plan->scope, $now);
            $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
            if ($result->ranOutOfTime()) {
                // The run, not the source, had no time left: keep the last outcome and health, the source stays due.
                $this->log->log('warning', 'source.outOfTime', ['source' => $plan->id(), 'scope' => $plan->scope->value, 'durationMs' => $durationMs]);
            } else {
                $this->keep($plan, $result, $now, $durationMs);
            }
            if ($process !== null) {
                $this->marker->finish($process);
            }
        } catch (\Throwable $e) {
            // Health or run marker not writable: this source's bookkeeping is lost, the rest of the run goes on. A
            // result that was kept still reaches its layer.
            $this->log->log('critical', 'source.exception', ['source' => $plan->id(), 'scope' => $plan->scope->value, 'error' => $e::class . ': ' . $e->getMessage()]);
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * Keeps outcome and health. An outcome that cannot be stored (e.g. a value the archive cannot serialize) is the
     * failure of this source only, not of the rest of the run.
     */
    private function keep(SourcePlan $plan, SourceResult $result, UtcInstant $now, int $durationMs): void
    {
        try {
            $this->outcomes->save($plan, $result);
        } catch (\Throwable $e) {
            $this->log->log('critical', 'source.unstorable', ['source' => $plan->id(), 'scope' => $plan->scope->value, 'error' => $e::class . ': ' . $e->getMessage()]);
            $result = SourceResult::failure($plan->source->description, 'internal: outcome not storable');
        }
        $this->health->record($plan, $result, $now, $durationMs);
    }

    /** Before a run of a lane or a fallback: a source its last run left unfinished counts as failed (6.2). */
    public function heal(string $process, UtcInstant $now): void
    {
        $key = $this->marker->unfinished($process);
        if ($key === null) {
            return;
        }
        $split = strrpos($key, '-');
        $scope = $split === false ? null : Scope::tryFrom(substr($key, $split + 1));
        if ($split !== false && $scope !== null) {
            $this->recordCrash(substr($key, 0, $split), $scope, $this->marker->startedAt($process), $now);
        }
        $this->log->log('critical', 'source.crashed', ['process' => $process, 'source' => $key]);
        $this->marker->finish($process);
    }

    /** Under the source lock: a source another process is running right now is not marked as crashed. */
    private function recordCrash(string $sourceId, Scope $scope, ?UtcInstant $startedAt, UtcInstant $now): void
    {
        $lock = $this->locks->acquire('source-' . $sourceId . '-' . $scope->value);
        if ($lock === null) {
            return;
        }
        try {
            $this->health->recordCrash($sourceId, $scope, $startedAt, $now);
        } finally {
            $lock->release();
        }
    }
}
