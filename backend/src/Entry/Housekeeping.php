<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Paths;
use CommonSight\Infrastructure\Storage\SnapshotCleaner;
use CommonSight\Model\Layer\LayerTarget;

/** Cleans up: old snapshot versions, orphaned temporary files, oversized logs (Architecture 5.2). */
final class Housekeeping
{
    private const LOG_MAX_BYTES = 5_000_000;
    private const LOG_KEEP_BYTES = 1_000_000;
    private const TEMP_MAX_AGE_SEC = 3600;

    public function __construct(private readonly Paths $paths, private readonly SnapshotCleaner $snapshots) {}

    /**
     * @param list<LayerTarget> $targets
     * @return array{snapshots: int, temporary: int, logs: int}
     */
    public function run(array $targets, int $now): array
    {
        return [
            'snapshots' => $this->snapshots->clean($targets, $now),
            'temporary' => $this->removeTemporaryFiles($now),
            'logs' => $this->truncateLogs(),
        ];
    }

    private function removeTemporaryFiles(int $now): int
    {
        $removed = 0;
        foreach ([$this->paths->data, $this->paths->state, $this->paths->cache] as $root) {
            // Down to the stores of the plugins (cache/plugins/<id>/), which write their files the same way.
            foreach (glob($root . '/{,*/,*/*/}.tmp-*', GLOB_BRACE) ?: [] as $file) {
                if ($now - (int) filemtime($file) > self::TEMP_MAX_AGE_SEC && unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    private function truncateLogs(): int
    {
        $truncated = 0;
        foreach (glob($this->paths->logs . '/*.log') ?: [] as $log) {
            $size = (int) filesize($log);
            if ($size <= self::LOG_MAX_BYTES) {
                continue;
            }
            // Read and rewrite under one lock: the logger appends with LOCK_EX, so no line written meanwhile is lost
            // (cron.log and tiles.log are written by the shell without a lock and may lose a line).
            $handle = fopen($log, 'r+');
            if ($handle === false || !flock($handle, LOCK_EX)) {
                continue;
            }
            fseek($handle, -self::LOG_KEEP_BYTES, SEEK_END);
            fgets($handle);
            $tail = (string) stream_get_contents($handle);
            rewind($handle);
            fwrite($handle, $tail);
            ftruncate($handle, strlen($tail));
            flock($handle, LOCK_UN);
            fclose($handle);
            $truncated++;
        }

        return $truncated;
    }
}
