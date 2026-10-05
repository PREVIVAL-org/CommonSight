<?php

declare(strict_types=1);

namespace CommonSight\Config;

/**
 * What the operator sets for one source in config.php (sources.<id>): switched off with a reason, another lane, a
 * longer interval, another rank within its layer, and the secrets the source needs (F-17; concept: sources as plugins,
 * 5, 6.1, D6).
 */
final readonly class SourceSettings
{
    public function __construct(
        public bool $enabled = true,
        public ?string $lane = null,
        public ?int $intervalSec = null,
        public ?int $order = null,
        /** @var array<string, string> name -> value, e.g. an API key */
        public array $secrets = [],
        /** why the operator switched the source off, shown by fetcher.php check */
        public ?string $reason = null,
    ) {}
}
