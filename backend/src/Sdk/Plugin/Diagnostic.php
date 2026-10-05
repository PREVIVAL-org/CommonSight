<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/**
 * Something a run of a source noticed and that belongs in the log, e.g. a failed request or a rejected record. Plugins
 * do not log themselves; the core writes the diagnostics of an outcome with source and scope (Architecture 1.3.4).
 */
final readonly class Diagnostic
{
    private const LEVELS = ['info', 'notice', 'warning'];

    /** @param array<string, scalar|null> $context */
    public function __construct(public string $level, public string $event, public array $context = [])
    {
        if (!in_array($level, self::LEVELS, true) || preg_match('/^[a-z][A-Za-z.]{1,60}$/', $event) !== 1) {
            throw new \InvalidArgumentException('Invalid diagnostic: ' . $level . ' ' . $event);
        }
    }
}
