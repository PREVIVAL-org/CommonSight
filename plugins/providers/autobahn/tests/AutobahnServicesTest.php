<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Autobahn\Tests;

use CommonSight\Model\Item\TrafficCategory;
use CommonSight\Model\Item\TrafficNoticeItem;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Autobahn\AutobahnMapper;
use CommonSight\Plugin\Autobahn\AutobahnParser;
use CommonSight\Plugin\Autobahn\AutobahnRequest;
use CommonSight\Plugin\Autobahn\Record\RoadWarning;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-TR-DE-01, Q-TR-DE-02: warnings, closures and short-term roadworks of the Autobahn API, their kind in German. */
final class AutobahnServicesTest extends TestCase
{
    private ParseContext $context;

    protected function setUp(): void
    {
        $this->context = new ParseContext(Scope::DE, UtcInstant::fromIso('2026-10-04T12:00:00Z'));
    }

    public function testEveryMotorwayIsAskedForItsWarningsClosuresAndRoadworks(): void
    {
        $urls = array_map(static fn($r): string => $r->url, (new AutobahnRequest())->requestsFor(Scope::DE, $this->context->now));

        self::assertCount(3 * count(AutobahnRequest::ROADS), $urls);
        self::assertContains('https://verkehr.autobahn.de/o/autobahn/A1/services/closure', $urls);
        self::assertContains('https://verkehr.autobahn.de/o/autobahn/A93/services/roadworks', $urls);
    }

    /** Current closures only; of the roadworks only the current short-term ones. */
    public function testAnnouncedItemsAndLongTermRoadworksAreSkipped(): void
    {
        $closures = $this->parse(['closure' => [$this->entry('c1', 'CLOSURE', false), $this->entry('c2', 'CLOSURE', true)]]);
        $roadworks = $this->parse(['roadworks' => [
            $this->entry('r1', 'SHORT_TERM_ROADWORKS', false),
            $this->entry('r2', 'ROADWORKS', false),
            $this->entry('r3', 'SHORT_TERM_ROADWORKS', true),
        ]]);

        self::assertSame(['c1'], array_map(static fn(RoadWarning $r): string => $r->identifier, $closures));
        self::assertSame(['r1'], array_map(static fn(RoadWarning $r): string => $r->identifier, $roadworks));
    }

    public function testAResponseWithoutAKnownListIsUnreadable(): void
    {
        $this->expectException(UnreadableResponse::class);
        $this->parse(['webcam' => []]);
    }

    /** The API's English codes become German words; an unknown traffic code a general disruption. */
    public function testTheKindIsGerman(): void
    {
        self::assertSame('Sperrung', $this->kind(null, 'CLOSURE'));
        self::assertSame('Gesperrte Anschlussstelle', $this->kind(null, 'CLOSURE_ENTRY_EXIT'));
        self::assertSame('Tagesbaustelle', $this->kind(null, 'SHORT_TERM_ROADWORKS'));
        self::assertSame('Stau', $this->kind('QUEUING_TRAFFIC', 'WARNING'));
        self::assertSame('Stockender Verkehr', $this->kind('SLOW_TRAFFIC', 'WARNING'));
        self::assertSame('Verkehrsstörung', $this->kind('UNSPECIFIED_ABNORMAL_TRAFFIC', 'WARNING'));
        self::assertNull($this->kind(null, 'WARNING'));
    }

    /** The map colours jams apart (category), whatever the source calls them. */
    public function testTheCategorySeparatesJamsFromClosuresAndRoadworks(): void
    {
        self::assertSame(TrafficCategory::Jam, $this->notice('SLOW_TRAFFIC', 'WARNING')->category);
        self::assertSame(TrafficCategory::Closure, $this->notice(null, 'CLOSURE_ENTRY_EXIT')->category);
        self::assertSame(TrafficCategory::Roadworks, $this->notice(null, 'SHORT_TERM_ROADWORKS')->category);
        self::assertSame(TrafficCategory::Other, $this->notice('UNSPECIFIED_ABNORMAL_TRAFFIC', 'WARNING')->category);
        self::assertSame('jam', $this->notice('QUEUING_TRAFFIC', 'WARNING')->jsonSerialize()['category']);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $body
     *
     * @return list<RoadWarning>
     */
    private function parse(array $body): array
    {
        $records = (new AutobahnParser(new JsonBody(), new TextCleaner()))->parse(Fixtures::body((string) json_encode($body)), $this->context)->records;

        return array_values(array_filter($records, static fn(object $r): bool => $r instanceof RoadWarning));
    }

    /** @return array<string, mixed> */
    private function entry(string $id, string $displayType, bool $future): array
    {
        return [
            'identifier' => $id,
            'future' => $future,
            'display_type' => $displayType,
            'title' => 'A1 | Kamen-Zentrum - Dortmund/Unna',
            'startTimestamp' => '2026-10-04T08:00:00+02:00',
            'point' => '51.6,7.6',
            'description' => ['Vollsperrung'],
        ];
    }

    private function kind(?string $trafficType, string $displayType): ?string
    {
        return $this->notice($trafficType, $displayType)->noticeType;
    }

    private function notice(?string $trafficType, string $displayType): TrafficNoticeItem
    {
        $record = new RoadWarning('x', 'A1', '', [], '2026-10-04T08:00:00+02:00', '51.6,7.6', $trafficType, $displayType);
        $item = (new AutobahnMapper(new UtcTimeParser()))->map($record, $this->context);
        self::assertInstanceOf(TrafficNoticeItem::class, $item);

        return $item;
    }
}
