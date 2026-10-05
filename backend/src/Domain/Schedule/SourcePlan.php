<?php

declare(strict_types=1);

namespace CommonSight\Domain\Schedule;

use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;

/**
 * A source in one of its scopes as the scheduler sees it: the unit of scheduling, with the lane and the effective
 * interval from its description and the configuration (concept: sources as plugins, 6.1).
 */
final readonly class SourcePlan
{
    public function __construct(
        public RegisteredSource $source,
        public Scope $scope,
        public string $lane,
        public int $intervalSec,
    ) {}

    public function id(): string
    {
        return $this->source->description->id;
    }

    /** Key of the source in this scope, e.g. "pegelonline-DE", for its health, outcome and lock. */
    public function key(): string
    {
        return $this->id() . '-' . $this->scope->value;
    }

    /** The layer this source contributes to, in its scope. */
    public function target(): LayerTarget
    {
        return new LayerTarget(LayerId::from($this->source->description->layer), $this->scope);
    }
}
