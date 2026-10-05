<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Sdk\Source\CollectedResponses;
use PHPUnit\Framework\TestCase;

/** The failure summary of a source run: real failures before those caused by the time budget of the run. */
final class CollectedResponsesTest extends TestCase
{
    public function testRealFailuresComeFirst(): void
    {
        $budget = 'budgetExhausted: Time budget of the run used up';
        $collected = new CollectedResponses([], [$budget, $budget, $budget, 'tls: certificate expired', 'clientError: HTTP 404'], 5);

        self::assertSame('tls: certificate expired; clientError: HTTP 404; ' . $budget, $collected->failureSummary());
        self::assertSame($budget . '; ' . $budget, (new CollectedResponses([], [$budget, $budget], 2))->failureSummary());
        self::assertSame('no response', (new CollectedResponses([], [], 0))->failureSummary());
    }
}
