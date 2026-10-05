<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the current water gauges of eHYD (Q-WA-AT-01). */
final class EhydRequest implements SourceRequest
{
    public const SOURCE_ID = 'ehyd';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest('https://ehyd.gv.at/services/PegelAktuell/json', 'application/json', self::SOURCE_ID)];
    }
}
