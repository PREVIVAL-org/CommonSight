<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the WFS request to dwd:Warnungen_Landkreise, paged at 500 features (Q-W-DE-01). */
final class DwdWarningRequest implements SourceRequest
{
    public const SOURCE_ID = 'dwd-warnings';
    public const PAGE_SIZE = 500;
    private const URL = 'https://maps.dwd.de/geoserver/dwd/ows';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [$this->page(0)];
    }

    public function page(int $startIndex): HttpRequest
    {
        return HttpRequest::withQuery(self::URL, [
            'service' => 'WFS',
            'version' => '2.0.0',
            'request' => 'GetFeature',
            'typeNames' => 'dwd:Warnungen_Landkreise',
            'outputFormat' => 'application/json',
            'count' => self::PAGE_SIZE,
            'startIndex' => $startIndex,
        ], 'application/json', self::SOURCE_ID, 'page-' . $startIndex);
    }
}
