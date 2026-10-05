<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Recording;

use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Port\HttpClient;

/** Answers requests from recordings instead of the network; a request without recording fails like a 404. */
final class ReplayHttpClient implements HttpClient
{
    /** @var array<string, RecordedExchange> */
    private array $bySignature = [];

    /** @param list<Cassette> $cassettes */
    public function __construct(array $cassettes)
    {
        foreach ($cassettes as $cassette) {
            foreach ($cassette->exchanges as $exchange) {
                $this->bySignature[$exchange->signature] = $exchange;
            }
        }
    }

    public function fetchAll(array $requests): array
    {
        return array_map(function (HttpRequest $request): HttpResponse|HttpFailure {
            $exchange = $this->bySignature[RecordedExchange::signatureOf($request)] ?? null;

            return $exchange === null
                ? new HttpFailure($request, FailureKind::ClientError, 'HTTP 404 (no recording for ' . RecordedExchange::signatureOf($request) . ')')
                : new HttpResponse($request, $exchange->status, $exchange->body, $exchange->headers);
        }, $requests);
    }
}
