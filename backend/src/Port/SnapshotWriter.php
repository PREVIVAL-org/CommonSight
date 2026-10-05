<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;

/** Stores a serialized snapshot under its version, atomically and precompressed (F-03). */
interface SnapshotWriter
{
    /** @return string path relative to the data directory, e.g. AT/warnings.3f9a1c2e.json */
    public function write(LayerId $layer, Scope $scope, string $version, string $json): string;

    /** Whether the snapshot behind a path from write() is still there (housekeeping may have removed it). */
    public function exists(string $file): bool;
}
