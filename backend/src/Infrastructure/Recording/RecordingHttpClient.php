<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Recording;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Port\HttpClient;

/** Passes requests to the real client and keeps every successful response, for tools/record/record-plugin.php. */
final class RecordingHttpClient implements HttpClient
{
    /** @var list<RecordedExchange> */
    public array $exchanges = [];

    /** @param \Closure(string): string $trim shortens a body, so that the test data stays small */
    public function __construct(private readonly HttpClient $client, private readonly \Closure $trim) {}

    public function fetchAll(array $requests): array
    {
        $responses = $this->client->fetchAll($requests);
        foreach ($responses as $response) {
            if ($response instanceof HttpResponse) {
                $this->exchanges[] = new RecordedExchange(RecordedExchange::signatureOf($response->request), $response->status, $response->headers, ($this->trim)($response->body));
            }
        }

        return $responses;
    }
}
