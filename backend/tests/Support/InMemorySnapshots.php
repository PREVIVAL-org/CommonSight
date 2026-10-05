<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Port\SnapshotWriter;

/** Snapshot store in memory. */
final class InMemorySnapshots implements SnapshotWriter
{
    /** @var array<string, string> path -> JSON */
    public array $files = [];

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
