<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

/** Writes a file atomically: temporary file in the target directory, then rename() (F-03). */
final class AtomicFile
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new StorageError('Cannot create directory: ' . $directory);
        }
        $temporary = tempnam($directory, '.tmp-');
        if ($temporary === false) {
            throw new StorageError('Cannot create temporary file in ' . $directory);
        }
        if (file_put_contents($temporary, $contents) !== strlen($contents) || !chmod($temporary, 0644) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new StorageError('Cannot write file: ' . $path);
        }
    }

    /** @return string|null null if the file is missing */
    public function read(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
