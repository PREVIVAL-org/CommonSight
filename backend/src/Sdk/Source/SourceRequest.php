<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/** Describes which HTTP requests a source needs for a scope. */
interface SourceRequest
{
    /** @return list<HttpRequest> */
    public function requestsFor(Scope $scope, UtcInstant $now): array;
}
