<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk\Layer;

use CommonSight\Model\Geometry;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\NewsItem;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\ExpiredItemFilter;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\ItemLimit;
use CommonSight\Sdk\Layer\NewestFirstSorter;
use CommonSight\Sdk\Layer\RecentItemFilter;
use CommonSight\Sdk\Layer\SeveritySorter;
use CommonSight\Sdk\Layer\UrlDeduplicator;
use PHPUnit\Framework\TestCase;

/** Q-00, Q-03, Q-W-01, Q-W-AT-11, Q-NE-03: shared processing steps. */
final class PipelineStepsTest extends TestCase
{
    private UtcInstant $now;

    protected function setUp(): void
    {
        $this->now = UtcInstant::fromIso('2026-09-28T12:00:00Z');
    }

    public function testRemovesExpiredWarnings(): void
    {
        $items = [$this->warning('a', Severity::Minor, expires: '2026-09-28T12:00:00Z'), $this->warning('b', Severity::Minor, expires: '2026-09-28T12:00:01Z'), $this->warning('c', Severity::Minor)];

        self::assertSame(['b', 'c'], $this->ids((new ExpiredItemFilter())->apply($items, $this->now)));
    }

    public function testMergesDuplicatesKeepingGeometry(): void
    {
        $withArea = $this->warning('a', Severity::Minor)->withCommon(new ItemCommon('a', 'A', 'https://x.example/', geometry: Geometry::fromGeoJson('Point', [10, 50])));
        $result = (new ItemDeduplicator())->apply([$this->warning('a', Severity::Minor), $withArea, $this->warning('b', Severity::Minor)], $this->now);

        self::assertSame(['a', 'b'], $this->ids($result));
        self::assertNotNull($result[0]->common()->geometry);
    }

    /** D6: of two items with the same id the more critical one wins and keeps the geometry of the other. */
    public function testTheMoreCriticalDuplicateWins(): void
    {
        $withArea = $this->warning('a', Severity::Minor)->withCommon(new ItemCommon('a', 'A', 'https://x.example/', geometry: Geometry::fromGeoJson('Point', [10, 50])));
        $result = (new ItemDeduplicator())->apply([$withArea, $this->warning('a', Severity::Severe), $this->warning('a', Severity::Moderate)], $this->now);

        self::assertCount(1, $result);
        self::assertInstanceOf(WarningItem::class, $result[0]);
        self::assertSame(Severity::Severe, $result[0]->severity, 'the more cautious message');
        self::assertNotNull($result[0]->common()->geometry, 'geometry taken over from the first');
    }

    public function testSortsBySeverityThenEarliestOnset(): void
    {
        $items = [
            $this->warning('minor', Severity::Minor),
            $this->warning('severe-late', Severity::Severe, onset: '2026-09-28T15:00:00Z'),
            $this->warning('unknown', Severity::Unknown),
            $this->warning('severe-early', Severity::Severe, onset: '2026-09-28T13:00:00Z'),
            $this->warning('extreme', Severity::Extreme),
        ];

        self::assertSame(['extreme', 'severe-early', 'severe-late', 'minor', 'unknown'], $this->ids((new SeveritySorter())->apply($items, $this->now)));
    }

    public function testNewsWindowDedupeSortAndLimit(): void
    {
        $items = [
            $this->news('old', 'https://n.example/1', '2026-09-25T11:59:59Z'),
            $this->news('a', 'https://n.example/2', '2026-09-28T08:00:00Z'),
            $this->news('a-dup', 'https://n.example/2', '2026-09-28T09:00:00Z'),
            $this->news('b', 'https://n.example/3', '2026-09-28T10:00:00Z'),
        ];
        $recent = (new RecentItemFilter(72 * 3600))->apply($items, $this->now);
        $unique = (new UrlDeduplicator())->apply($recent, $this->now);
        $sorted = (new NewestFirstSorter())->apply($unique, $this->now);

        self::assertSame(['b', 'a'], $this->ids($sorted));
        self::assertSame(['b'], $this->ids((new ItemLimit(1))->apply($sorted, $this->now)));
    }

    private function warning(string $id, Severity $severity, ?string $expires = null, ?string $onset = null): WarningItem
    {
        return new WarningItem(
            new ItemCommon($id, $id, 'https://x.example/'),
            new Msg('hazard.weather'),
            $severity,
            '',
            $onset === null ? null : UtcInstant::fromIso($onset),
            $expires === null ? null : UtcInstant::fromIso($expires),
            [],
            new CatalogTerm('weather'),
        );
    }

    private function news(string $id, string $url, string $time): NewsItem
    {
        return new NewsItem(new ItemCommon($id, $id, $url, time: UtcInstant::fromIso($time)), new CatalogTerm('weather'), new CatalogTerm('orf'));
    }

    /**
     * @param list<Item> $items
     * @return list<string>
     */
    private function ids(array $items): array
    {
        return array_map(static fn(Item $i): string => $i->common()->id, $items);
    }
}
