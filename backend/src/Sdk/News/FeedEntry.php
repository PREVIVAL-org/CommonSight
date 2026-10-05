<?php

declare(strict_types=1);

namespace CommonSight\Sdk\News;

use CommonSight\Model\Value\UtcInstant;

/** An entry of an RSS, RDF or Atom feed. */
final readonly class FeedEntry
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public ?string $link,
        public ?UtcInstant $published,
    ) {}
}
