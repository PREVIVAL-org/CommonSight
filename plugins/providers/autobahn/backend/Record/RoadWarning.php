<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Autobahn\Record;

/**
 * A warning, closure or roadworks of the Autobahn API in the source's vocabulary; point as "lat,lon"; displayType
 * WARNING, CLOSURE, SHORT_TERM_ROADWORKS, …
 */
final readonly class RoadWarning
{
    /** @param list<string> $description description lines */
    public function __construct(
        public string $identifier,
        public string $title,
        public string $subtitle,
        public array $description,
        public ?string $startTimestamp,
        public ?string $point,
        public ?string $abnormalTrafficType,
        public ?string $displayType = null,
    ) {}
}
