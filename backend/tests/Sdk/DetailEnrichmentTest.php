<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\DetailEnrichment;
use CommonSight\Sdk\Source\DetailPlan;
use CommonSight\Sdk\Source\DetailSource;
use CommonSight\Tests\Support\FakeHttpClient;
use CommonSight\Tests\Support\InMemoryPluginStore;
use PHPUnit\Framework\TestCase;

/** Details fetched one by one (Architecture 4.9): kept until the message ends, unusable answers not asked again at once. */
final class DetailEnrichmentTest extends TestCase
{
    public function testAnAnswerWithoutDetailIsNotAskedAgainEveryRunSoTheOthersGetTheirTurn(): void
    {
        $http = new FakeHttpClient();
        $http->respond('https://example.org/detail/', '{}');
        $enrichment = new DetailEnrichment($http, new InMemoryPluginStore(), new DetailPlan(2));
        $records = [(object) ['id' => 'a'], (object) ['id' => 'b'], (object) ['id' => 'c']];
        $now = UtcInstant::fromIso('2026-10-03T12:00:00Z');

        $enrichment->enrich($this->source(), $records, $now);
        [$enriched] = $enrichment->enrich($this->source(), $records, $now->plusSeconds(60));

        self::assertSame(['a', 'b', 'c'], array_map(static fn(HttpRequest $r): string => basename($r->url), $http->requests), 'a and b have no detail; c gets its turn');
        self::assertSame([false, false, true], array_map(static fn(object $r): bool => isset($r->detail), $enriched));
        $enrichment->enrich($this->source(), $records, $now->plusSeconds(1801));
        self::assertCount(5, $http->requests, 'after 30 min a and b are asked again');
    }

    public function testADetailThatNoLongerAppliesLeavesTheRecordWithoutIt(): void
    {
        $store = new InMemoryPluginStore();
        $store->write('test-details', '{"broken":{"expiresAt":9999999999,"detail":{"text":"x"}}}');
        $enrichment = new DetailEnrichment(new FakeHttpClient(), $store, new DetailPlan(0));

        [$enriched, $diagnostics] = $enrichment->enrich($this->source(), [(object) ['id' => 'broken']], UtcInstant::fromIso('2026-10-03T12:00:00Z'));

        self::assertFalse(isset($enriched[0]->detail));
        self::assertSame('details.unusable', $diagnostics[0]->event);
    }

    private function source(): DetailSource
    {
        return new class implements DetailSource {
            public function cacheName(): string
            {
                return 'test-details';
            }

            public function cacheKey(object $record): ?string
            {
                return $record->id;
            }

            public function request(object $record): ?HttpRequest
            {
                return new HttpRequest('https://example.org/detail/' . $record->id, 'application/json', 'test');
            }

            public function extract(object $record, HttpResponse $response): ?array
            {
                return $record->id === 'c' ? ['text' => 'Detail c'] : null;
            }

            public function apply(object $record, array $detail): object
            {
                if ($record->id === 'broken') {
                    throw new \InvalidArgumentException('no longer valid');
                }

                return (object) ['id' => $record->id, 'detail' => $detail['text']];
            }

            public function expiresAt(object $record): ?UtcInstant
            {
                return null;
            }
        };
    }
}
