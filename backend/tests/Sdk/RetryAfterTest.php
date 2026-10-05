<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Application\HealthRecorder;
use CommonSight\Domain\Schedule\BackoffPolicy;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\HttpClient;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceRun;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\SourceRequest;
use CommonSight\Tests\Support\FixedRandom;
use CommonSight\Tests\Support\InMemoryHealth;
use PHPUnit\Framework\TestCase;

/** Concept "sources as plugins", 6: a Retry-After of the provider reaches the backoff of the source. */
final class RetryAfterTest extends TestCase
{
    public function testTheLongestRetryAfterOfTheFailedRequestsIsPassedOn(): void
    {
        $http = new class implements HttpClient {
            public function fetchAll(array $requests): array
            {
                return array_map(static fn(HttpRequest $r): HttpFailure => new HttpFailure($r, FailureKind::RateLimited, 'HTTP 429', $r->key === 'a' ? 300 : 900), $requests);
            }
        };
        $request = new class implements SourceRequest {
            public function requestsFor(Scope $scope, UtcInstant $now): array
            {
                return [new HttpRequest('https://example.org/a', 'application/json', 'demo', 'a'), new HttpRequest('https://example.org/b', 'application/json', 'demo', 'b')];
            }
        };
        $parser = new class implements SourceParser {
            public function parse(HttpResponse $response, ParseContext $context): ParseResult
            {
                throw new \LogicException('no response to parse');
            }
        };
        $mapper = new class implements ItemMapper {
            public function map(object $record, ParseContext $context): ?Item
            {
                return null;
            }
        };

        $outcome = (new RequestParseMap($http, new SourceParts($request, $parser, $mapper)))->run(new SourceRun(Scope::DE, UtcInstant::fromIso('2026-09-28T12:00:00Z')));

        self::assertFalse($outcome->succeeded());
        self::assertSame(900, $outcome->retryAfterSec);
    }

    public function testTheBackoffOfTheSourceHonoursIt(): void
    {
        $health = new InMemoryHealth();
        $description = new SourceDescription(id: 'demo', name: 'Demo', attribution: new Attribution('Demo', 'https://example.org/'), layer: 'water', scopes: [Scope::DE], schedule: new SourceSchedule(600), expectations: SourceExpectations::measurements());
        $plan = new SourcePlan(new RegisteredSource($description, static fn(): SourcePlugin => throw new \LogicException('not needed')), Scope::DE, 'heavy', 600);
        $now = UtcInstant::fromIso('2026-09-28T12:00:00Z');

        (new HealthRecorder($health, new BackoffPolicy(), new FixedRandom(0.0), 'r1'))->record($plan, SourceResult::failure($description, 'rateLimited: HTTP 429', 900), $now, 120);

        self::assertSame('2026-09-28T12:15:00Z', $health->read('demo', Scope::DE)?->backoffUntil?->toIso());
        self::assertSame(120, $health->read('demo', Scope::DE)?->typicalDurationMs);
    }
}
