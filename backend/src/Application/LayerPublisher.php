<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\LockFactory;
use CommonSight\Port\Logger;
use CommonSight\Port\StateReader;

/**
 * Assembles a layer from the latest outcomes of its sources and records the result: new snapshot only for new content,
 * state always (F-03, F-05, Architecture 4.3). The layer lock protects only this assembly; an error in it affects only
 * this layer (concept: sources as plugins, 6.1, 6.2).
 */
final class LayerPublisher
{
    /** An assembly takes milliseconds to about a second (the largest layers); longer means a hanging process. */
    private const LOCK_WAIT_SEC = 5.0;

    public function __construct(
        private readonly LockFactory $locks,
        private readonly StateReader $states,
        private readonly LayerComposer $composer,
        private readonly LayerSchedule $schedule,
        private readonly ResultRecorder $recorder,
        private readonly Logger $log,
    ) {}

    /** @param list<SourcePlan> $plans the sources of the layer in this scope, by rank */
    public function publish(LayerTarget $target, array $plans, UtcInstant $now, string $trigger): RunOutcome
    {
        // Another process assembling the same layer may have read an older outcome of a source that just ran: wait for
        // it and assemble again from the latest outcomes, instead of leaving the new one unpublished.
        $lock = $this->locks->acquireWaiting('layer-' . $target->name(), self::LOCK_WAIT_SEC);
        if ($lock === null) {
            return RunOutcome::Locked;
        }
        $started = hrtime(true);
        try {
            $outcome = $this->publishLocked($target, $plans, $now);
        } catch (\Throwable $e) {
            // Writing failed (e.g. a snapshot folder without write permission): this layer fails, the others of the
            // run are still published.
            $this->log->log('critical', 'layer.exception', ['layer' => $target->layer->value, 'scope' => $target->scope->value, 'error' => $e::class . ': ' . $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $outcome = RunOutcome::Failed;
        } finally {
            $lock->release();
        }
        $this->log->log($outcome === RunOutcome::Failed ? 'error' : 'info', 'layer.run', [
            'layer' => $target->layer->value,
            'scope' => $target->scope->value,
            'trigger' => $trigger,
            'outcome' => $outcome->value,
            'durationMs' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);

        return $outcome;
    }

    /** A layer without any state yet is assembled once, e.g. one without sources, which shows "setup". */
    public function publishIfMissing(LayerTarget $target, UtcInstant $now): ?RunOutcome
    {
        return $this->states->read($target->layer, $target->scope) === null ? $this->publish($target, [], $now, 'initial') : null;
    }

    /** @param list<SourcePlan> $plans */
    private function publishLocked(LayerTarget $target, array $plans, UtcInstant $now): RunOutcome
    {
        $previous = $this->states->read($target->layer, $target->scope);
        [$intervalSec, $staleAfter] = $this->schedule->of($plans, $now);
        try {
            $assembly = $this->composer->compose($target, $plans, $now);
        } catch (\Throwable $e) {
            // A programming error must not endanger the last good state: the layer counts as failed.
            $this->log->log('critical', 'layer.exception', ['layer' => $target->layer->value, 'scope' => $target->scope->value, 'error' => $e::class . ': ' . $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);

            return $this->recorder->recordFailure($target, new Msg('error.internal'), $previous, $now, $intervalSec, $staleAfter);
        }
        if ($assembly->snapshot === null) {
            return $this->recorder->recordFailure($target, $assembly->failure ?? new Msg('error.allSourcesFailed'), $previous, $now, $intervalSec, $staleAfter);
        }

        return $this->recorder->recordSuccess($target, $assembly->snapshot, $previous, $now, $intervalSec, $staleAfter);
    }
}
