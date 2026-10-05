<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to all PEGELONLINE stations with time series and current measurement (Q-WA-DE-01). */
final class PegelonlineRequest implements SourceRequest
{
    public const SOURCE_ID = 'pegelonline';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [HttpRequest::withQuery(
            'https://www.pegelonline.wsv.de/webservices/rest-api/v2/stations.json',
            ['includeTimeseries' => 'true', 'includeCurrentMeasurement' => 'true'],
            'application/json',
            self::SOURCE_ID,
        )];
    }
}
