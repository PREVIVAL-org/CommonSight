<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd\Tests;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Ehyd\AustrianWaterAssessor;
use CommonSight\Plugin\Ehyd\EhydMapper;
use CommonSight\Plugin\Ehyd\EhydParser;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-WA-AT-*: parser and mapper edge cases of eHYD. */
final class EhydParserTest extends TestCase
{
    private ParseContext $context;

    protected function setUp(): void
    {
        $this->context = new ParseContext(Scope::AT, UtcInstant::fromIso('2026-09-28T12:00:00Z'));
    }

    public function testBrokenJsonIsUnreadable(): void
    {
        $this->expectException(UnreadableResponse::class);

        (new EhydParser(new JsonBody(), new NumberParser()))->parse(Fixtures::body('{"features": [}'), $this->context);
    }

    /** Q-WA-AT-02: decimal comma, local time Europe/Vienna, discharge for parameter Q. */
    public function testEhydDecimalCommaLocalTimeAndDischarge(): void
    {
        $response = Fixtures::json(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [13.8029, 47.1348]],
            'properties' => ['hzbnr' => 203786, 'messstelle' => 'Tamsweg', 'gewasser' => 'Taurach', 'hd' => 'Salzburg', 'parameter' => 'Q', 'wert' => '5,13', 'einheit' => 'm³/s', 'zp' => '2026-09-28T12:30:00', 'gesamtcode' => 410, 'internet' => 'javascript:x'],
        ]]]);
        $record = (new EhydParser(new JsonBody(), new NumberParser()))->parse($response, $this->context)->records[0];
        $item = (new EhydMapper(new AustrianWaterAssessor(), new UtcTimeParser(), new SafeUrl(), new \DateTimeZone('Europe/Vienna')))->map($record, $this->context);

        self::assertInstanceOf(MeasurementItem::class, $item);
        self::assertSame(5.13, $item->value);
        self::assertEquals(new CatalogTerm('discharge'), $item->quantity);
        self::assertSame('2026-09-28T10:30:00Z', $item->common->time?->toIso());
        self::assertSame('https://ehyd.gv.at/', $item->common->url, 'F-12: invalid link → source page');
        self::assertSame('elevated', $item->assessment->level->value);
        self::assertSame(410, $item->assessment->sourceValue);
    }

    /** A gauge out of service (no value, no time) is skipped, not a broken record that would mark the source partial. */
    public function testGaugeWithoutValueAndTimeIsSkipped(): void
    {
        $response = Fixtures::json(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [12.77, 46.83]],
            'properties' => ['hzbnr' => 212134, 'messstelle' => 'Lienz', 'gewasser' => 'Isel', 'parameter' => 'W', 'wert' => null, 'zp' => null, 'gesamtcode' => 930],
        ]]]);
        $statistics = (new EhydParser(new JsonBody(), new NumberParser()))->parse($response, $this->context)->statistics;

        self::assertSame([0, 0, 1], [$statistics->valid, $statistics->rejected, $statistics->skipped]);
    }

    /** eHYD returns some water levels in "m ü.A": referenced to sea level instead of the gauge zero. */
    public function testEhydHeightAboveAdriaIsSeaLevelReference(): void
    {
        $response = Fixtures::json(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [9.7, 47.5]],
            'properties' => ['hzbnr' => 1, 'messstelle' => 'Bregenz', 'gewasser' => 'Bodensee', 'parameter' => 'W', 'wert' => '395,12', 'einheit' => 'm ü.A', 'zp' => '2026-09-28T12:30:00', 'gesamtcode' => 230],
        ]]]);
        $record = (new EhydParser(new JsonBody(), new NumberParser()))->parse($response, $this->context)->records[0];
        $item = (new EhydMapper(new AustrianWaterAssessor(), new UtcTimeParser(), new SafeUrl(), new \DateTimeZone('Europe/Vienna')))->map($record, $this->context);

        self::assertInstanceOf(MeasurementItem::class, $item);
        self::assertSame('reference.seaLevel', $item->reference->key);
    }
}
