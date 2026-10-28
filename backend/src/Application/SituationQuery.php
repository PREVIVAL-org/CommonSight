<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Status\ConditionalResponse;
use CommonSight\Domain\Status\SituationView;
use CommonSight\Model\Decoded;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\SnapshotReader;
use CommonSight\Port\StateReader;

/**
 * Reads the state of the warnings layer of every country and the situation level from its snapshot (concept: situation
 * level, L-D4). The snapshots are read on every request (DE about 10 KB, CH about 150 KB); the answer may be kept 60 s.
 */
final class SituationQuery
{
    public function __construct(
        private readonly StateReader $states,
        private readonly SnapshotReader $snapshots,
        private readonly SituationView $view,
        private readonly ConditionalResponse $conditional,
        /** the layer whose key figures hold stats.situation */
        private readonly LayerId $layer,
    ) {}

    public function answer(UtcInstant $now, ?string $ifNoneMatch): StatusAnswer
    {
        $countries = [];
        foreach (Scope::countries() as $country) {
            $state = $this->states->read($this->layer, $country);
            $countries[$country->value] = $this->view->country($state, $this->situation($state), $now);
        }
        // From what is answered, not from the versions alone: a snapshot that could not be read must not stay cached.
        $etag = $this->conditional->etag($countries);
        if ($this->conditional->isNotModified($ifNoneMatch, $etag)) {
            return new StatusAnswer(304, $etag, '', []);
        }
        $body = json_encode($this->view->response($countries, $now), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new StatusAnswer(200, $etag, $body, []);
    }

    private function situation(?LayerState $state): ?Decoded
    {
        if ($state === null || $state->file === null) {
            return null;
        }
        $json = $this->snapshots->read($state->file);
        if ($json === null) {
            return null;
        }
        // A snapshot of an older release has no situation; the country is then unknown until the next assembly.
        $situation = Decoded::of(json_decode($json, true))->get('stats', 'situation');

        return $situation->isArray() ? $situation : null;
    }
}
