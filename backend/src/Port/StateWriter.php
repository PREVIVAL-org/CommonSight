<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\State\LayerState;

/** Writes the state file of a layer atomically. */
interface StateWriter
{
    public function write(LayerState $state): void;
}
