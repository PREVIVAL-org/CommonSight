<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Layer\LayerCatalog;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Snapshot\Assembly;
use CommonSight\Domain\Snapshot\SnapshotAssembler;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\UtcInstant;

/**
 * Assembles a layer from the latest outcome of each of its sources, also of sources in other lanes; a source without an
 * outcome yet counts as pending (concept: sources as plugins, 6). Needs no network.
 */
final class LayerComposer
{
    public function __construct(
        private readonly LayerCatalog $catalog,
        private readonly OutcomeArchive $outcomes,
        private readonly SnapshotAssembler $assembler,
    ) {}

    /** @param list<SourcePlan> $plans the sources of the layer in this scope, by rank */
    public function compose(LayerTarget $target, array $plans, UtcInstant $now): Assembly
    {
        $results = array_map(
            fn(SourcePlan $plan): SourceResult => $this->outcomes->load($plan) ?? SourceResult::pending($plan->source->description),
            $plans,
        );

        return $this->assembler->assemble($this->catalog->get($target->layer, $target->scope), $results, $now);
    }
}
