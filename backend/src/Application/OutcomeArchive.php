<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Port\OutcomeStore;

/**
 * Keeps the latest result of each source in each scope for the assembly of its layer (concept: sources as plugins, 6).
 * Only the release that wrote a result reads it again: after an update the classes may differ, and the source is due
 * at once anyway (DueSourcePolicy).
 */
final class OutcomeArchive
{
    public function __construct(private readonly OutcomeStore $store, private readonly string $release) {}

    public function save(SourcePlan $plan, SourceResult $result): void
    {
        $this->store->write($plan->key(), serialize(['release' => $this->release, 'result' => $result]));
    }

    /** The latest result of this release, or null. */
    public function load(SourcePlan $plan): ?SourceResult
    {
        $encoded = $this->store->read($plan->key());
        if ($encoded === null) {
            return null;
        }
        try {
            $data = @unserialize($encoded);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($data) || ($data['release'] ?? null) !== $this->release || !($data['result'] ?? null) instanceof SourceResult) {
            return null;
        }

        return $data['result'];
    }
}
