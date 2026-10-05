<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaKp;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the planetary Kp index of NOAA SWPC (Q-SP-01). */
final class SwpcRequest implements SourceRequest
{
    public const SOURCE_ID = 'noaa-kp';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest('https://services.swpc.noaa.gov/products/noaa-planetary-k-index.json', 'application/json', self::SOURCE_ID)];
    }
}
