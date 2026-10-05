<?php

declare(strict_types=1);

namespace CommonSight\Sdk\News;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to a news feed; country-independent (Q-NE-01, Q-NE-02). */
final class NewsFeedRequest implements SourceRequest
{
    public function __construct(private readonly NewsFeed $feed) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest($this->feed->url, 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.1', $this->feed->sourceId)];
    }
}
