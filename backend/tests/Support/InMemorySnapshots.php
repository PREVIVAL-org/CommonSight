<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Port\SnapshotReader;
use CommonSight\Port\SnapshotWriter;

/** Snapshot store in memory. */
final class InMemorySnapshots implements SnapshotWriter, SnapshotReader
{
    /** @var array<string, string> path -> JSON */
    public array $files = [];

    public function read(string $file): ?string
    {
        return $this->files[$file] ?? null;
    }

    public function exists(string $file): bool
    {
        return isset($this->files[$file]);
    }

    public function write(LayerId $layer, Scope $scope, string $version, string $json): string
    {
        $path = sprintf('%s/%s.%s.json', $scope->value, $layer->value, $version);
        $this->files[$path] = $json;

        return $path;
    }
}
