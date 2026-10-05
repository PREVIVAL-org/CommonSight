<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\RunMarker;

/**
 * The source a process is running and since when, in state/sources/running-<process>.txt ("<key>\n<ISO time>"); removed
 * when the source has finished.
 */
final class RunMarkerFile implements RunMarker
{
    public function __construct(private readonly string $stateDir, private readonly AtomicFile $files) {}

    public function start(string $process, string $sourceKey, UtcInstant $startedAt): void
    {
        $this->files->write($this->path($process), $sourceKey . "\n" . $startedAt->toIso());
    }

    public function finish(string $process): void
    {
        $path = $this->path($process);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function unfinished(string $process): ?string
    {
        $key = trim($this->lines($process)[0] ?? '');

        return $key === '' ? null : $key;
    }

    public function startedAt(string $process): ?UtcInstant
    {
        $time = trim($this->lines($process)[1] ?? '');
        try {
            return $time === '' ? null : UtcInstant::fromIso($time);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return list<string> */
    private function lines(string $process): array
    {
        $content = $this->files->read($this->path($process));

        return $content === null ? [] : explode("\n", $content);
    }

    private function path(string $process): string
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $process) !== 1) {
            throw new \InvalidArgumentException('Invalid process name: ' . $process);
        }

        return $this->stateDir . '/sources/running-' . $process . '.txt';
    }
}
