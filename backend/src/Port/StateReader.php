<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;

/** Reads the state file of a layer. */
interface StateReader
{
    /** @return LayerState|null null if there is no state file yet */
    public function read(LayerId $layer, Scope $scope): ?LayerState;
}
