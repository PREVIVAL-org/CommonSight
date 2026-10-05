<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Msg;
use CommonSight\Model\Value\Scope;

/**
 * The parts of a source of the common shape (concept: sources as plugins, 4.3): which requests, how a response is
 * read, how a record becomes an item; optionally follow-up pages, details per record and own deficit checks.
 */
final readonly class SourceParts
{
    /**
     * @param list<DeficitDetector> $detectors
     * @param (\Closure(Scope): ?Msg)|null $coverage what the source covers in a scope, shown below the note of its layer
     */
    public function __construct(
        public SourceRequest $request,
        public SourceParser $parser,
        public ItemMapper $mapper,
        public ?Paging $paging = null,
        public ?DetailSource $detail = null,
        public array $detectors = [],
        public ?\Closure $coverage = null,
    ) {}
}
