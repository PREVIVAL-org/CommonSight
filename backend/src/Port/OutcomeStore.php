<?php

declare(strict_types=1);

namespace CommonSight\Port;

/**
 * Keeps the latest outcome of each source in each scope, so that a layer can be assembled from the outcomes of all its
 * sources, also of other lanes (concept: sources as plugins, 6). Stores the encoded outcome as it is.
 */
interface OutcomeStore
{
    /** @param string $key source and scope, e.g. "pegelonline-DE" */
    public function read(string $key): ?string;

    public function write(string $key, string $outcome): void;
}
