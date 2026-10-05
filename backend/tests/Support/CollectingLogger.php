<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\Logger;

/** Logger that collects entries. */
final class CollectingLogger implements Logger
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $entries = [];

    public function log(string $level, string $event, array $context = []): void
    {
        $this->entries[] = [$level, $event, $context];
    }

    /** @return list<string> */
    public function events(): array
    {
        return array_map(static fn(array $e): string => $e[1], $this->entries);
    }
}
