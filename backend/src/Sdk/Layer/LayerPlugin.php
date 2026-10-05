<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Value\Scope;

/** A layer: in each of its scopes its processing steps, note, key figures, display name and link. */
interface LayerPlugin
{
    /** @param LayerSources $sources the sources of the layer in this scope, by rank */
    public function definition(Scope $scope, LayerSources $sources): LayerDefinition;
}
