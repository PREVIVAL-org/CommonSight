<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Oeamtc;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the German GeoRSS traffic feed of the ÖAMTC (Q-TR-AT-01); the feed traffic_informations is its English twin. */
final class OeamtcRequest implements SourceRequest
{
    public const SOURCE_ID = 'oeamtc';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest(
            'https://www.oeamtc.at/verkehrsservice/output/rss/oeamtc_verkehrsservice_oesterreich.xml',
            'application/rss+xml, application/xml;q=0.9, */*;q=0.1',
            self::SOURCE_ID,
        )];
    }
}
