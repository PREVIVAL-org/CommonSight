<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Eurdep;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the WFS request to the EURDEP values at BfS/IMIS, filtered by the country prefix of the scope (Q-RA-01). */
final class ImisRequest implements SourceRequest
{
    public const SOURCE_ID = 'eurdep';
    private const URL = 'https://www.imis.bfs.de/ogc/opendata/ows';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [HttpRequest::withQuery(self::URL, [
            'service' => 'WFS', 'version' => '1.0.0', 'request' => 'GetFeature', 'outputFormat' => 'application/json', 'maxFeatures' => 3000,
            'typeName' => 'opendata:eurdep_latestValue',
            'cql_filter' => sprintf("id LIKE '%s%%' AND analyzed_range_in_h=6", $scope->value),
        ], 'application/json', self::SOURCE_ID)];
    }
}
