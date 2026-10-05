<?php

declare(strict_types=1);

namespace CommonSight\Plugin\BfsOdl;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the WFS request to the latest hourly ODL values of BfS/IMIS (Q-RA-01). */
final class ImisRequest implements SourceRequest
{
    public const SOURCE_ID = 'bfs-odl';
    private const URL = 'https://www.imis.bfs.de/ogc/opendata/ows';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [HttpRequest::withQuery(self::URL, [
            'service' => 'WFS', 'version' => '1.0.0', 'request' => 'GetFeature', 'outputFormat' => 'application/json', 'maxFeatures' => 3000,
            'typeName' => 'opendata:odlinfo_odl_1h_latest',
        ], 'application/json', self::SOURCE_ID)];
    }
}
