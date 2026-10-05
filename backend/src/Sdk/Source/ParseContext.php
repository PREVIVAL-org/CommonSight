<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/** Scope and time of the run that a parser needs for its decisions (e.g. expired). */
final readonly class ParseContext
{
    public function __construct(public Scope $scope, public UtcInstant $now) {}
}
