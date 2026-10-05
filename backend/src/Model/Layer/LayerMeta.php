<?php

declare(strict_types=1);

namespace CommonSight\Model\Layer;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;

/**
 * Entry of the layer registry: scope and data from the border zone (contract/catalog/layers.json, generated from the
 * layer plugins). Interval and lane belong to the sources since they are scheduled one by one (concept: sources as
 * plugins, 6.1); the age from which a value counts as outdated belongs to the layer that checks it.
 */
final readonly class LayerMeta
{
    public function __construct(
        public LayerId $id,
        public bool $global,
        public bool $border = false,
    ) {}

    /** @return list<Scope> scopes for which this layer has snapshots */
    public function scopes(): array
    {
        if ($this->global) {
            return [Scope::Global];
        }

        return $this->border ? [...Scope::countries(), Scope::Border] : Scope::countries();
    }

    public function scopeFor(Scope $country): Scope
    {
        return $this->global ? Scope::Global : $country;
    }
}
