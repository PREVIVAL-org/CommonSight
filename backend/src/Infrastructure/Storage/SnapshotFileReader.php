<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Port\SnapshotReader;

/** Reads a snapshot from data/v1/<scope>/<layer>.<version>.json. */
final class SnapshotFileReader implements SnapshotReader
{
    public function __construct(private readonly string $dataDir, private readonly AtomicFile $files) {}

    public function read(string $file): ?string
    {
        return $this->files->read($this->dataDir . '/' . $file);
    }
}
