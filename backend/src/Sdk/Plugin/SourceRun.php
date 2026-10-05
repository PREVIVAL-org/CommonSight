<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/** Input of one run of a source: the scope to fetch and the time of the run (no plugin asks the clock itself). */
final readonly class SourceRun
{
    public function __construct(public Scope $scope, public UtcInstant $now) {}
}
