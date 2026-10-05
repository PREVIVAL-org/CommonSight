<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Port\HttpClient;

/** HTTP client that returns responses by URL prefix from a table and records all requests. */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param array<string, string|FailureKind> $responses URL prefix -> body or failure kind */
    public function __construct(private array $responses = []) {}

    public function respond(string $urlPrefix, string|FailureKind $body): void
    {
        $this->responses[$urlPrefix] = $body;
    }

    /** Whether a test response is set for this request. */
    public function answers(HttpRequest $request): bool
    {
        foreach (array_keys($this->responses) as $prefix) {
            if (str_starts_with($request->url, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function fetchAll(array $requests): array
    {
        array_push($this->requests, ...$requests);

        return array_map(function (HttpRequest $request): HttpResponse|HttpFailure {
            foreach ($this->responses as $prefix => $body) {
                if (str_starts_with($request->url, $prefix)) {
                    return $body instanceof FailureKind ? new HttpFailure($request, $body, 'test failure') : new HttpResponse($request, 200, $body);
                }
            }

            return new HttpFailure($request, FailureKind::ClientError, 'HTTP 404 (no test response)');
        }, $requests);
    }
}
