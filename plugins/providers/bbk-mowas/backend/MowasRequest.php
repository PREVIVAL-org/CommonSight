<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the requests to the overviews of the NINA feeds at warnung.bund.de, one per feed (Q-W-DE-03). */
final class MowasRequest implements SourceRequest
{
    public const SOURCE_ID = 'bbk-mowas';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        // The key names the feed, so that the parser knows where a response comes from.
        return array_map(static fn(NinaFeed $feed): HttpRequest => new HttpRequest($feed->overviewUrl(), 'application/json', self::SOURCE_ID, $feed->value), NinaFeed::cases());
    }
}
