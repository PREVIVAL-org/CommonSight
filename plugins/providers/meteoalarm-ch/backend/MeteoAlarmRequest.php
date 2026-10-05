<?php

declare(strict_types=1);

namespace CommonSight\Plugin\MeteoAlarm;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the MeteoAlarm Atom feed for Switzerland (Q-W-CH-01). */
final class MeteoAlarmRequest implements SourceRequest
{
    public const SOURCE_ID = 'meteoalarm-ch';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest(
            'https://feeds.meteoalarm.org/feeds/meteoalarm-legacy-atom-switzerland',
            // Without */* MeteoAlarm responds with 406 (checked on 2026-09-28).
            'application/atom+xml, application/xml;q=0.9, */*;q=0.1',
            self::SOURCE_ID,
        )];
    }
}
