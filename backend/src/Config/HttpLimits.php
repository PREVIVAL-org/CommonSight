<?php

declare(strict_types=1);

namespace CommonSight\Config;

/** Limits and user agent of the HTTP client (F-07, F-08, F-10). */
final readonly class HttpLimits
{
    /**
     * @param array<string, int> $maxBytesBySource size per response for single sources (config.php, over the plugin's own)
     * @param array<string, int> $requestTimeoutBySource time per request for single sources (config.php, over the plugin's own)
     */
    public function __construct(
        public int $connectTimeoutSec = 10,
        public int $requestTimeoutSec = 18,
        public int $maxBytes = 5_000_000,
        public array $maxBytesBySource = [],
        public int $perHostConcurrency = 4,
        public int $totalConcurrency = 12,
        public ?string $caFile = null,
        public string $userAgent = 'CommonSight/2.0 (+https://previval.org)',
        public array $requestTimeoutBySource = [],
    ) {
        if ($connectTimeoutSec < 1 || $requestTimeoutSec < 1 || $maxBytes < 1024 || $perHostConcurrency < 1 || $totalConcurrency < 1) {
            throw new ConfigError('http: time, size and concurrency limits must be positive');
        }
    }

    /**
     * The limits a plugin declares for itself: a value set for the source in the configuration keeps precedence, a
     * limit the plugin does not declare (null) stays the general one of the configuration.
     */
    public function withSourceDefaults(string $sourceId, ?int $requestTimeoutSec, ?int $maxBytes): self
    {
        return new self(
            $this->connectTimeoutSec,
            $this->requestTimeoutSec,
            $this->maxBytes,
            $this->maxBytesBySource + ($maxBytes === null ? [] : [$sourceId => $maxBytes]),
            $this->perHostConcurrency,
            $this->totalConcurrency,
            $this->caFile,
            $this->userAgent,
            $this->requestTimeoutBySource + ($requestTimeoutSec === null ? [] : [$sourceId => $requestTimeoutSec]),
        );
    }

    public function maxBytesFor(string $sourceId): int
    {
        return $this->maxBytesBySource[$sourceId] ?? $this->maxBytes;
    }

    public function requestTimeoutFor(string $sourceId): int
    {
        return $this->requestTimeoutBySource[$sourceId] ?? $this->requestTimeoutSec;
    }
}
