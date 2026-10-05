<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the warning status of GeoSphere Austria (Q-W-AT-01). */
final class GeoSphereStatusRequest implements SourceRequest
{
    public const SOURCE_ID = 'geosphere-warnings';
    public const API = 'https://warnungen.zamg.at/wsapp/api/';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest(self::API . 'getWarnstatus', 'application/json', self::SOURCE_ID)];
    }
}
