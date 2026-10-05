<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

use CommonSight\Config\HttpLimits;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;

/** Queue of the requests of a fetchAll: order, retries, parallelism per host and the results. */
final class RequestQueue
{
    /** @var list<array{int, HttpRequest, int, float}> slot, request, attempt, earliest start */
    private array $pending = [];
    /** @var array<int, HttpResponse|HttpFailure> */
    private array $results = [];
    /** @var array<string, int> */
    private array $perHost = [];

    /** @param list<HttpRequest> $requests */
    public function __construct(private readonly array $requests, private readonly HttpLimits $limits)
    {
        foreach ($requests as $index => $request) {
            $this->pending[] = [$index, $request, 1, 0.0];
        }
    }

    /** @return array{int, HttpRequest, int}|null */
    public function nextStartable(int $activeCount, float $now): ?array
    {
        if ($activeCount >= $this->limits->totalConcurrency) {
            return null;
        }
        foreach ($this->pending as $position => [$index, $request, $attempt, $notBefore]) {
            if ($notBefore <= $now && ($this->perHost[$this->host($request)] ?? 0) < $this->limits->perHostConcurrency) {
                array_splice($this->pending, $position, 1);

                return [$index, $request, $attempt];
            }
        }

        return null;
    }

    public function started(HttpRequest $request): void
    {
        $host = $this->host($request);
        $this->perHost[$host] = ($this->perHost[$host] ?? 0) + 1;
    }

    public function stopped(HttpRequest $request): void
    {
        $this->perHost[$this->host($request)]--;
    }

    public function retry(int $index, HttpRequest $request, int $attempt, float $notBefore): void
    {
        $this->pending[] = [$index, $request, $attempt, $notBefore];
    }

    public function finish(int $index, HttpResponse|HttpFailure $result): void
    {
        $this->results[$index] = $result;
    }

    /** @param array<int, mixed> $active */
    public function isDone(array $active): bool
    {
        return $active === [] && $this->pending === [];
    }

    /** @return list<HttpResponse|HttpFailure> */
    public function results(): array
    {
        $ordered = [];
        foreach (array_keys($this->requests) as $index) {
            $ordered[] = $this->results[$index];
        }

        return $ordered;
    }

    private function host(HttpRequest $request): string
    {
        return (string) parse_url($request->url, PHP_URL_HOST);
    }
}
