<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Http\CurlTransfer;
use CommonSight\Infrastructure\Http\ResponseHeaderParser;
use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

/** A transfer: headers of the last response only, status mapped to the failure kinds, Retry-After passed on (F-05). */
final class CurlTransferTest extends TestCase
{
    public function testARateLimitPassesTheRetryAfterOfTheProviderOn(): void
    {
        $transfer = $this->transfer();
        // A redirect first: its headers must not count.
        $this->headers($transfer, ['HTTP/1.1 302 Found', 'Retry-After: 9999', 'Location: https://example.org/b', '', 'HTTP/1.1 429 Too Many Requests', 'Retry-After: 120', '']);

        $result = $transfer->resultFor(429, 1_790_000_000);

        self::assertInstanceOf(HttpFailure::class, $result);
        self::assertSame(FailureKind::RateLimited, $result->kind);
        self::assertSame(120, $result->retryAfterSec);
    }

    public function testStatusesMapToTheirKinds(): void
    {
        self::assertInstanceOf(HttpResponse::class, $this->transfer()->resultFor(200, 0));
        $server = $this->transfer()->resultFor(503, 0);
        $client = $this->transfer()->resultFor(404, 0);
        self::assertInstanceOf(HttpFailure::class, $server);
        self::assertInstanceOf(HttpFailure::class, $client);
        self::assertSame(FailureKind::ServerError, $server->kind);
        self::assertSame(FailureKind::ClientError, $client->kind);
    }

    /** Architecture 5.1: a timeout the run's budget cut short is not the source's failure, its own timeout is. */
    public function testATimeoutIsTheSourcesFailureOnlyWithItsOwnLimit(): void
    {
        $own = $this->transfer()->result(CURLE_OPERATION_TIMEDOUT);
        $clipped = $this->transfer();
        $clipped->budgetLimited = true;
        $clippedResult = $clipped->result(CURLE_OPERATION_TIMEDOUT);

        self::assertInstanceOf(HttpFailure::class, $own);
        self::assertInstanceOf(HttpFailure::class, $clippedResult);
        self::assertSame(FailureKind::Timeout, $own->kind);
        self::assertSame(FailureKind::BudgetExhausted, $clippedResult->kind);
    }

    public function testTooManyHeaderLinesAreIgnored(): void
    {
        $transfer = $this->transfer();
        $this->headers($transfer, ['HTTP/1.1 200 OK', ...array_fill(0, 300, 'X-Filler: x'), 'ETag: "late"']);

        $result = $transfer->resultFor(200, 0);

        self::assertInstanceOf(HttpResponse::class, $result);
        self::assertNull($result->headers->etag, 'line 302 is beyond the limit of 200');
    }

    private function transfer(): CurlTransfer
    {
        return new CurlTransfer(curl_init(), new HttpRequest('https://example.org/a', 'application/json', 'test'), 1000, new ResponseHeaderParser());
    }

    /** @param list<string> $lines */
    private function headers(CurlTransfer $transfer, array $lines): void
    {
        $handle = curl_init();
        foreach ($lines as $line) {
            $transfer->header($handle, $line . "\r\n");
        }
    }
}
