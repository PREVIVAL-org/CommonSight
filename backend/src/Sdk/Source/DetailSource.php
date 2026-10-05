<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;

/**
 * Describes the individual query per record of a source (detail text, warning area) and how the result supplements the record.
 *
 * Details are cached as arrays so that the cache (Architecture 4.9) stays source-independent.
 */
interface DetailSource
{
    /** Name of the cache, e.g. geosphere-details. */
    public function cacheName(): string;

    /** Key of the detail; if the message changes, the key changes. null = no detail needed. */
    public function cacheKey(object $record): ?string;

    /** Request for the detail, or null if it cannot be queried. */
    public function request(object $record): ?HttpRequest;

    /**
     * Evaluates the detail response for exactly this record; null = no matching information.
     *
     * @return array<string, mixed>|null
     */
    public function extract(object $record, HttpResponse $response): ?array;

    /** @param array<string, mixed> $detail */
    public function apply(object $record, array $detail): object;

    /** Until when the detail stays in the cache (end or expiry of the message). */
    public function expiresAt(object $record): ?UtcInstant;
}
