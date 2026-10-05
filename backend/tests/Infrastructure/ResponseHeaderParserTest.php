<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Http\ResponseHeaderParser;
use PHPUnit\Framework\TestCase;

/** Response headers for conditional requests, waiting times and paging. */
final class ResponseHeaderParserTest extends TestCase
{
    private const NOW = 1_790_000_000;

    public function testReadsTheUsableHeadersCaseInsensitively(): void
    {
        $headers = (new ResponseHeaderParser())->parse([
            'ETag: "abc"',
            'last-modified: Sat, 03 Oct 2026 10:00:00 GMT',
            'LINK: <https://api.example/?page=2>; rel="next"',
            'Content-Type: application/json; charset=utf-8',
            'X-Other: ignored',
        ], self::NOW);

        self::assertSame('"abc"', $headers->etag);
        self::assertSame('Sat, 03 Oct 2026 10:00:00 GMT', $headers->lastModified);
        self::assertSame('<https://api.example/?page=2>; rel="next"', $headers->link);
        self::assertSame('application/json; charset=utf-8', $headers->contentType);
        self::assertNull($headers->retryAfterSec);
    }

    public function testRetryAfterAsSecondsOrHttpDate(): void
    {
        $parser = new ResponseHeaderParser();
        $date = gmdate('D, d M Y H:i:s', self::NOW + 120) . ' GMT';

        self::assertSame(90, $parser->parse(['Retry-After: 90'], self::NOW)->retryAfterSec);
        self::assertSame(120, $parser->parse(['Retry-After: ' . $date], self::NOW)->retryAfterSec);
        self::assertSame(0, $parser->parse(['Retry-After: ' . gmdate('D, d M Y H:i:s', self::NOW - 60) . ' GMT'], self::NOW)->retryAfterSec);
        self::assertNull($parser->parse(['Retry-After: soon'], self::NOW)->retryAfterSec);
    }

    public function testIgnoresLinesWithoutNameAndEmptyValues(): void
    {
        $headers = (new ResponseHeaderParser())->parse(['garbage', 'ETag:'], self::NOW);

        self::assertNull($headers->etag);
    }
}
