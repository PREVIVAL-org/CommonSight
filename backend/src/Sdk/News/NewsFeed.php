<?php

declare(strict_types=1);

namespace CommonSight\Sdk\News;

/** A news feed as a news plugin describes it: its source, the feed term of the catalog, display name and address. */
final readonly class NewsFeed
{
    public function __construct(
        public string $sourceId,
        /** term of the catalog (category feed), also the prefix of the item ids */
        public string $feed,
        public string $name,
        public string $url,
    ) {}
}
