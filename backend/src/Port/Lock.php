<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** A held lock. */
interface Lock
{
    public function release(): void;
}
