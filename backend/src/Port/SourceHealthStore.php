<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\Scope;

/** Reads and writes the health of a source in a scope (concept: sources as plugins, 6). */
interface SourceHealthStore
{
    /** @return SourceHealth|null null if the source has not run in this scope yet */
    public function read(string $sourceId, Scope $scope): ?SourceHealth;

    public function write(SourceHealth $health): void;
}
