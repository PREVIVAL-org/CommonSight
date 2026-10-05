<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Port\MemoryUsage;

/** Memory with fixed values, by default plenty of room. */
final class FixedMemory implements MemoryUsage
{
    public function __construct(private readonly int $used = 10_000_000, private readonly ?int $limit = null) {}

    public function usedBytes(): int
    {
        return $this->used;
    }

    public function limitBytes(): ?int
    {
        return $this->limit;
    }
}
