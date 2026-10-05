<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Plugin;

use CommonSight\Infrastructure\Storage\AtomicFile;
use CommonSight\Port\Clock;
use CommonSight\Port\PluginStore;

/** Store of one plugin as files in its own directory (cache/plugins/<id>/), one file per key, written atomically. */
final class FilePluginStore implements PluginStore
{
    public function __construct(private readonly string $directory, private readonly AtomicFile $files, private readonly Clock $clock) {}

    public function read(string $key): ?string
    {
        $contents = $this->files->read($this->path($key));
        $entry = $contents === null ? null : json_decode($contents, true);
        if (!is_array($entry) || !is_string($entry['value'] ?? null)) {
            return null;
        }
        $expiresAt = $entry['expiresAt'] ?? null;

        return is_int($expiresAt) && $expiresAt <= $this->clock->now()->timestamp ? null : $entry['value'];
    }

    public function write(string $key, string $value, ?int $ttlSec = null): void
    {
        $expiresAt = $ttlSec === null ? null : $this->clock->now()->timestamp + $ttlSec;
        $this->files->write($this->path($key), json_encode(['expiresAt' => $expiresAt, 'value' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Keys may contain any character; the file name is their hash. */
    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }
}
