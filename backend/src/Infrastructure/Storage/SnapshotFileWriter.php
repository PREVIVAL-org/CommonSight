<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Port\SnapshotWriter;

/** Stores a snapshot as data/v1/<scope>/<layer>.<version>.json, plus precompressed as .gz and, if possible, .br (Architecture 4.6). */
final class SnapshotFileWriter implements SnapshotWriter
{
    public function __construct(private readonly string $dataDir, private readonly AtomicFile $files) {}

    public function exists(string $file): bool
    {
        return is_file($this->dataDir . '/' . $file);
    }

    private const BROTLI_LEVEL = 9;

    public function write(LayerId $layer, Scope $scope, string $version, string $json): string
    {
        $relative = sprintf('%s/%s.%s.json', $scope->value, $layer->value, $version);
        $path = $this->dataDir . '/' . $relative;
        $gzip = gzencode($json, 9);
        if ($gzip === false) {
            throw new StorageError('gzip failed: ' . $relative);
        }
        $this->files->write($path . '.gz', $gzip);
        if (function_exists('brotli_compress')) {
            // Level 9, not 11: about 60 times faster (the layer lock is held meanwhile; water border 2.1 s -> 32 ms)
            // for some 15 % more bytes.
            $brotli = brotli_compress($json, self::BROTLI_LEVEL, BROTLI_TEXT);
            if (is_string($brotli)) {
                $this->files->write($path . '.br', $brotli);
            }
        }
        $this->files->write($path, $json);

        return $relative;
    }
}
