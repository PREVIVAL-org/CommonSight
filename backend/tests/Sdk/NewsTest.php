<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\News\NewsFeedParser;
use CommonSight\Sdk\News\NewsTopicClassifier;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-NE-04, F-09: topic precedence and safe reading of the news feeds. */
final class NewsTest extends TestCase
{
    private ParseContext $context;

    protected function setUp(): void
    {
        $this->context = new ParseContext(Scope::AT, UtcInstant::fromIso('2026-09-28T12:00:00Z'));
    }

    /** Q-NE-04: the order of the checks is the precedence. */
    public function testNewsTopicsInPriorityOrder(): void
    {
        $classifier = new NewsTopicClassifier();

        self::assertEquals(new CatalogTerm('infrastructure'), $classifier->classify('Stromausfall nach Sturm'));
        self::assertEquals(new CatalogTerm('weather'), $classifier->classify('Hochwasser im Süden, Angriff abgewehrt'));
        self::assertEquals(new CatalogTerm('conflict'), $classifier->classify('Raketenangriff auf Kiew'));
        self::assertNull($classifier->classify('Bundesliga: Bayern gewinnt'));
        self::assertEquals(new CatalogTerm('conflict'), $classifier->classify('Nato-Gipfel in Den Haag'));
        self::assertEquals(new CatalogTerm('conflict'), $classifier->classify('Iranische Drohnen über dem Golf'));
        self::assertNull($classifier->classify('Senator besucht Tirana'), 'names hidden in other words');
        self::assertNull($classifier->classify('Sturm Graz gewinnt das Derby'), 'a football club');
        self::assertEquals(new CatalogTerm('weather'), $classifier->classify('Sturmtief zieht über Graz'));
        self::assertNull($classifier->classify('Ansturm auf Konzerttickets'), 'a rush is no storm');
        self::assertNull($classifier->classify('Regierung will Inflation in den Griff kriegen'), 'kriegen (to get) is no war');
        self::assertNull($classifier->classify('Kostenexplosion bei Mieten'), 'rising costs are no blast');
        self::assertNull($classifier->classify('Tarifkonflikt bei der Bahn'), 'a pay dispute is no armed conflict');
        self::assertEquals(new CatalogTerm('infrastructure'), $classifier->classify('Gasexplosion in Wohnhaus'));
        self::assertEquals(new CatalogTerm('conflict'), $classifier->classify('Bürgerkrieg im Sudan'));
        self::assertEquals(new CatalogTerm('conflict'), $classifier->classify('Kriegsgefahr wächst'));
    }

    /** F-09: XML with a DTD is not processed. */
    public function testFeedWithDoctypeIsRejected(): void
    {
        $this->expectException(UnreadableResponse::class);

        (new NewsFeedParser(new SafeXmlReader(), new UtcTimeParser(), new TextCleaner()))->parse(
            Fixtures::body('<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel><item><title>&x;</title></item></channel></rss>'),
            $this->context,
        );
    }
}
