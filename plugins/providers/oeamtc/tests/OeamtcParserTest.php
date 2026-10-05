<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Oeamtc\Tests;

use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Oeamtc\OeamtcParser;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Text\StableId;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** D-18: parser edge cases of the ÖAMTC feed. */
final class OeamtcParserTest extends TestCase
{
    private ParseContext $context;

    protected function setUp(): void
    {
        $this->context = new ParseContext(Scope::AT, UtcInstant::fromIso('2026-09-28T12:00:00Z'));
    }

    /** D-18: ÖAMTC labels Vienna local time as "GMT"; it is read in Europe/Vienna. */
    public function testOeamtcTimesAreViennaLocalTime(): void
    {
        $xml = '<?xml version="1.0"?><rss xmlns:georss="http://www.georss.org/georss"><channel><language>2057</language>'
            . '<lastBuildDate>Mon, 28 Sep 2026 16:30:53 GMT</lastBuildDate><item><guid>1</guid><title>A1</title>'
            . '<pubDate>Mon, 28 Sep 2026 16:33:58 GMT</pubDate><georss:point>48.2 16.37</georss:point></item></channel></rss>';
        $result = (new OeamtcParser(new SafeXmlReader(), new UtcTimeParser(), new TextCleaner(), new StableId()))->parse(Fixtures::body($xml), $this->context);

        self::assertSame('2026-09-28T14:30:53Z', $result->sourceUpdatedAt?->toIso());
        self::assertSame('2026-09-28T14:33:58Z', $result->records[0]->pubDate?->toIso());
        self::assertSame('en', $result->records[0]->language);
    }

    /** The German feed names its language as a regional tag (de-at). */
    public function testRegionalLanguageTagsAreRecognised(): void
    {
        $xml = '<?xml version="1.0"?><rss xmlns:georss="http://www.georss.org/georss"><channel><language>de-at</language>'
            . '<item><guid>1</guid><title>A1 West Autobahn, Salzburg Richtung Rosenheim</title><category>Stau</category>'
            . '<pubDate>Mon, 28 Sep 2026 16:33:58 GMT</pubDate><georss:point>48.2 16.37</georss:point></item></channel></rss>';
        $result = (new OeamtcParser(new SafeXmlReader(), new UtcTimeParser(), new TextCleaner(), new StableId()))->parse(Fixtures::body($xml), $this->context);

        self::assertSame('de', $result->records[0]->language);
    }
}
