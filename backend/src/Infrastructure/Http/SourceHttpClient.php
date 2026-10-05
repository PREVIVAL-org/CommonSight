<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Port\HttpClient;

/**
 * The HTTP client of one plugin: marks every request with the plugin's source id, so that the limits of that source
 * apply and logs name it, whatever id the plugin set itself.
 */
final class SourceHttpClient implements HttpClient
{
    public function __construct(private readonly HttpClient $client, private readonly string $sourceId) {}

    public function fetchAll(array $requests): array
    {
        return $this->client->fetchAll(array_map(
            fn(HttpRequest $r): HttpRequest => $r->sourceId === $this->sourceId
                ? $r
                : new HttpRequest($r->url, $r->accept, $this->sourceId, $r->key, $r->body, $r->headers, $r->secrets),
            $requests,
        ));
    }
}
