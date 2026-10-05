<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Text;

/** Builds a stable ID from parts if the source does not provide one. */
final class StableId
{
    public function fromParts(string $prefix, string ...$parts): string
    {
        return $prefix . ':' . substr(hash('sha256', implode("\x1f", $parts)), 0, 16);
    }
}
