<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Memory of the running process: what is used and what the limit allows. */
interface MemoryUsage
{
    public function usedBytes(): int;

    /** The memory limit in bytes, null without a limit. */
    public function limitBytes(): ?int;
}
