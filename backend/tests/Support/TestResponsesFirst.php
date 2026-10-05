<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Port\HttpClient;

/**
 * HTTP client of a plugin in the layer tests: a response a test sets explicitly wins (e.g. "the source answers with an
 * empty list"); every other request is answered from the plugin's own recordings.
 */
final class TestResponsesFirst implements HttpClient
{
    public function __construct(private readonly FakeHttpClient $testResponses, private readonly HttpClient $recordings) {}

    public function fetchAll(array $requests): array
    {
        return array_map(
            fn(HttpRequest $request) => ($this->testResponses->answers($request) ? $this->testResponses : $this->recordings)->fetchAll([$request])[0],
            $requests,
        );
    }
}
