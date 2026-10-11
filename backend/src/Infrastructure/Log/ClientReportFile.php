<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Log;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\ClientReportLog;

/**
 * One JSON line per error report in a file per day (`logs/client-YYYY-MM-DD.log`), so housekeeping can remove them by
 * age (Architecture 6.6). A full day drops further reports: a broken release or a flood never fills the disk.
 */
final class ClientReportFile implements ClientReportLog
{
    public const PATTERN = 'client-*.log';

    public function __construct(private readonly string $directory, private readonly int $maxBytesPerDay = 2_000_000) {}

    public function append(array $report, UtcInstant $now): bool
    {
        $path = $this->directory . '/client-' . gmdate('Y-m-d', $now->timestamp) . '.log';
        clearstatcache(true, $path);
        if (is_file($path) && (int) filesize($path) >= $this->maxBytesPerDay) {
            return false;
        }
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }
        $line = json_encode(['time' => $now->toIso()] + $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (@file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('[commonsight] client report not writable: ' . $path);
        }

        return true;
    }

    /** Removes the files of days older than `$keepDays`; returns how many. */
    public function removeOlderThan(int $keepDays, int $now): int
    {
        $oldest = gmdate('Y-m-d', $now - $keepDays * 86_400);
        $removed = 0;
        foreach (glob($this->directory . '/' . self::PATTERN) ?: [] as $file) {
            $day = substr(basename($file), 7, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && $day < $oldest && unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
