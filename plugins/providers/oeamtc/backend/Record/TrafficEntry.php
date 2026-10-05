<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Oeamtc\Record;

use CommonSight\Model\Value\UtcInstant;

/** An entry of the ÖAMTC GeoRSS feed; georss:point and georss:line as pairs "lat lon". */
final readonly class TrafficEntry
{
    public function __construct(
        public string $guid,
        public string $title,
        public string $description,
        public ?string $link,
        public ?UtcInstant $pubDate,
        public string $category,
        public string $point,
        public string $line,
        public ?string $language,
    ) {}
}
