<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Reads a stored snapshot. */
interface SnapshotReader
{
    /** @return string|null the JSON of the snapshot, null if the file is missing */
    public function read(string $file): ?string;
}
