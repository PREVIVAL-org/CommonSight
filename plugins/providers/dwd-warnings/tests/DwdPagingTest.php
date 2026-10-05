<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd\Tests;

use CommonSight\Plugin\Dwd\DwdPaging;
use CommonSight\Plugin\Dwd\DwdWarningRequest;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\ParseStatistics;
use PHPUnit\Framework\TestCase;

/** Q-W-DE-01: the DWD warnings are fetched completely, in pages of 500. */
final class DwdPagingTest extends TestCase
{
    /** Q-W-DE-01: fetch all warnings completely, pages of 500. */
    public function testDwdPagingRequestsRemainingPages(): void
    {
        $request = new DwdWarningRequest();
        $first = new ParseResult([], new ParseStatistics(0, 0, 0), null, 1234);

        $urls = array_map(static fn($r): string => $r->url, (new DwdPaging($request))->followUpRequests($request->page(0), $first));

        self::assertCount(2, $urls);
        self::assertStringContainsString('startIndex=500', $urls[0]);
        self::assertStringContainsString('startIndex=1000', $urls[1]);
        self::assertSame([], (new DwdPaging($request))->followUpRequests($request->page(0), new ParseResult([], new ParseStatistics(0, 0, 0), null, 500)));
    }
}
