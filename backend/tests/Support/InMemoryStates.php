<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Port\StateReader;
use CommonSight\Port\StateWriter;

/** Status files in memory. */
final class InMemoryStates implements StateReader, StateWriter
{
    /** @var array<string, LayerState> */
    public array $states = [];

    public function read(LayerId $layer, Scope $scope): ?LayerState
    {
        return $this->states[$scope->value . '/' . $layer->value] ?? null;
    }

    public function write(LayerState $state): void
    {
        $this->states[$state->scope->value . '/' . $state->layer->value] = $state;
    }
}
