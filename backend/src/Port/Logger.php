<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Writes a structured log entry (Architecture 12.3). */
interface Logger
{
    /** @param array<string, scalar|null|array<mixed>> $context */
    public function log(string $level, string $event, array $context = []): void;
}
