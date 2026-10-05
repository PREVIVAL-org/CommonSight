<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;

/**
 * Describes a layer in a scope: processing steps, notice and key figures; without behavior. Its sources are not part of
 * it: they name their layer themselves and are found through the source directory.
 */
final readonly class LayerDefinition
{
    /** @param list<PipelineStep> $steps */
    public function __construct(
        public LayerId $layer,
        public Scope $scope,
        public string $sourceName,
        public string $sourceUrl,
        public Msg $note,
        public array $steps,
        public StatsBuilder $stats,
    ) {}
}
