<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Value\UtcInstant;

/**
 * Records which source a process is running, so that a run that dies (memory, fatal error) is noticed by the next one
 * and counted as a failure of that source (self-healing, concept: sources as plugins, 6.2).
 */
interface RunMarker
{
    /** @param string $process e.g. the lane "fast" */
    public function start(string $process, string $sourceKey, UtcInstant $startedAt): void;

    public function finish(string $process): void;

    /** The source key of a run of this process that never finished, or null. */
    public function unfinished(string $process): ?string;

    /** When the unfinished run started; null if unknown (a marker of an older release). */
    public function startedAt(string $process): ?UtcInstant;
}
