<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Port\OutcomeStore;

/** The latest outcome of a source per scope in cache/sources/<id>-<scope>.bin, written atomically. */
final class OutcomeFile implements OutcomeStore
{
    public function __construct(private readonly string $cacheDir, private readonly AtomicFile $files) {}

    public function read(string $key): ?string
    {
        return $this->files->read($this->path($key));
    }

    public function write(string $key, string $outcome): void
    {
        $this->files->write($this->path($key), $outcome);
    }

    private function path(string $key): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*-[A-Za-z]+$/', $key) !== 1) {
            throw new \InvalidArgumentException('Invalid outcome key: ' . $key);
        }

        return $this->cacheDir . '/sources/' . $key . '.bin';
    }
}
