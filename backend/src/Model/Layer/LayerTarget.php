<?php

declare(strict_types=1);

namespace CommonSight\Model\Layer;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;

/** A concrete layer in a scope, e.g. warnings for AT. */
final readonly class LayerTarget
{
    public function __construct(public LayerId $layer, public Scope $scope) {}

    public function name(): string
    {
        return $this->scope->value . '-' . $this->layer->value;
    }
}
