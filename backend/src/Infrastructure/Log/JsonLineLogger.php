<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Log;

use CommonSight\Port\Logger;

/**
 * Writes one JSON line per entry with time, level, event, context and peak memory of the process (F-13, F-14, Architecture 12.3).
 */
final class JsonLineLogger implements Logger
{
    public function __construct(private readonly string $path, private readonly string $process) {}

    public function log(string $level, string $event, array $context = []): void
    {
        $line = json_encode([
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'event' => $event,
            'process' => $this->process,
            'memPeakMb' => round(memory_get_peak_usage(true) / 1_048_576, 1),
        ] + $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        if (@file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            // Without a writable log the entry goes to PHP's error log instead of being lost.
            error_log('[commonsight] ' . $line);
        }
    }
}
