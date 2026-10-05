<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Snapshot\ContentHash;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Msg;
use CommonSight\Model\Snapshot;
use CommonSight\Model\State\LastError;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\SnapshotWriter;
use CommonSight\Port\StateWriter;

/**
 * Records the result of an assembly: new snapshot only for new content, state file always; on failure the last good
 * snapshot stays (F-03, F-05, Architecture 4.3 step 6). Backoff belongs to the sources since they are scheduled one by
 * one (concept: sources as plugins, 6); the state carries the layer's interval and staleness from its sources.
 */
final class ResultRecorder
{
    public function __construct(
        private readonly ContentHash $hash,
        private readonly SnapshotWriter $snapshots,
        private readonly StateWriter $states,
    ) {}

    public function recordSuccess(LayerTarget $target, Snapshot $snapshot, ?LayerState $previous, UtcInstant $now, ?int $intervalSec, ?UtcInstant $staleAfter): RunOutcome
    {
        $version = $this->hash->version($snapshot);
        // Unchanged only if the snapshot is still there: a layer reinstalled after housekeeping removed its files is written again.
        $unchanged = $previous !== null && $previous->version === $version && $previous->file !== null && $this->snapshots->exists($previous->file);
        $file = $unchanged ? $previous->file : $this->snapshots->write($target->layer, $target->scope, $version, $this->hash->json($snapshot));
        $this->states->write(new LayerState(
            $target->layer,
            $target->scope,
            $version,
            $file,
            $snapshot->status,
            $unchanged ? $previous->generatedAt : $snapshot->generatedAt,
            $now,
            $snapshot->updatedAt,
            count($snapshot->items),
            $snapshot->issues,
            null,
            0,
            null,
            $intervalSec,
            $staleAfter,
        ));

        return $unchanged ? RunOutcome::Unchanged : RunOutcome::Updated;
    }

    public function recordFailure(LayerTarget $target, Msg $failure, ?LayerState $previous, UtcInstant $now, ?int $intervalSec, ?UtcInstant $staleAfter): RunOutcome
    {
        $state = $previous ?? LayerState::pending($target->layer, $target->scope);
        $this->states->write(new LayerState(
            $state->layer,
            $state->scope,
            $state->version,
            $state->file,
            $state->status,
            $state->generatedAt,
            $state->checkedAt,
            $state->sourceUpdatedAt,
            $state->itemCount,
            $state->issues,
            new LastError($now, $failure),
            $state->consecutiveFailures + 1,
            null,
            $intervalSec,
            $staleAfter,
        ));

        return RunOutcome::Failed;
    }
}
