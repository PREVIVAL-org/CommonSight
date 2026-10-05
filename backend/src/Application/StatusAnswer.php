<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\State\LayerState;

/** Response of the status endpoint with the states read, from which the fallback chooses. */
final readonly class StatusAnswer
{
    /** @param list<array{LayerTarget, ?LayerState}> $candidates */
    public function __construct(public int $status, public string $etag, public string $body, public array $candidates) {}
}
