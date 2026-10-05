<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaScales;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the NOAA space weather scales of SWPC (Q-SP-01). */
final class SwpcRequest implements SourceRequest
{
    public const SOURCE_ID = 'noaa-scales';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest('https://services.swpc.noaa.gov/products/noaa-scales.json', 'application/json', self::SOURCE_ID)];
    }
}
