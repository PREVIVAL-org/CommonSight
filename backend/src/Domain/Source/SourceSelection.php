<?php

declare(strict_types=1);

namespace CommonSight\Domain\Source;

/** Determines from the configuration which sources of a layer are fetched (F-17, V16). */
final class SourceSelection
{
    /** @param array<string, bool> $enabled switch per source ID; missing sources are enabled */
    public function __construct(private readonly array $enabled) {}

    public function isEnabled(string $sourceId): bool
    {
        return $this->enabled[$sourceId] ?? true;
    }
}
