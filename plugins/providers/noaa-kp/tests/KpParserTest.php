<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaKp\Tests;

use CommonSight\Model\Item\IndexItem;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\NoaaKp\KpMapper;
use CommonSight\Plugin\NoaaKp\KpParser;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-SP-03: the Kp index in both formats of NOAA SWPC. */
final class KpParserTest extends TestCase
{
    private ParseContext $context;

    protected function setUp(): void
    {
        $this->context = new ParseContext(Scope::AT, UtcInstant::fromIso('2026-09-28T12:00:00Z'));
    }

    /** Q-SP-03: Kp in the object and in the older table format, history of the last 16 values. */
    public function testKpParsesBothFormats(): void
    {
        $parser = new KpParser(new JsonBody(), new UtcTimeParser());
        $rows = [['time_tag', 'Kp', 'a_running', 'station_count']];
        for ($i = 0; $i < 20; $i++) {
            $rows[] = [sprintf('2026-09-26 %02d:00:00.000', $i % 24), (string) ($i % 9), '5', '8'];
        }
        $old = $parser->parse(Fixtures::json($rows), $this->context);
        $new = $parser->parse(Fixtures::response('noaa-kp', 'global.json'), $this->context);

        self::assertSame(0, $old->statistics->rejected, 'header row is no error');
        $item = (new KpMapper())->map($old->records[0], $this->context);
        self::assertInstanceOf(IndexItem::class, $item);
        self::assertCount(16, $item->history ?? []);
        self::assertSame(1.0, $item->value);
        self::assertGreaterThan(0, $new->statistics->valid);
    }
}
