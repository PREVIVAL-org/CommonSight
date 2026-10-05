<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk\Text;

use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\StableId;
use CommonSight\Sdk\Text\TextCleaner;
use PHPUnit\Framework\TestCase;

/** D-14 (plain text), F-12 (links only http/https), stable IDs and numbers with decimal comma. */
final class TextAndUrlTest extends TestCase
{
    public function testCleansHtmlEntitiesAndWhitespace(): void
    {
        $cleaner = new TextCleaner();

        self::assertSame('Straßen & Unterführungen überflutet', $cleaner->clean("<p>Straßen &amp; Unterführungen\n\n  überflutet</p>"));
        self::assertSame('Zeile eins Zeile zwei', $cleaner->clean('Zeile eins<br/>Zeile zwei'));
        self::assertSame('fett', $cleaner->clean('&lt;b&gt;fett&lt;/b&gt;'));
        self::assertSame('Im Wald (<50 m) und bei 3 < 5', $cleaner->clean('Im Wald (&lt;50 m) und bei 3 &lt; 5'), 'a decoded comparison is text, not a tag');
        self::assertSame('42', $cleaner->clean(42));
        self::assertSame('', $cleaner->clean(null));
        self::assertSame('', $cleaner->clean(['a']));
    }

    public function testAcceptsOnlyHttpAndHttpsLinks(): void
    {
        $url = new SafeUrl();

        self::assertSame('https://www.dwd.de/warnungen', $url->orFallback(' https://www.dwd.de/warnungen ', 'https://fallback.example/'));
        self::assertSame('http://www.oeamtc.at/verkehrsservice', $url->valid('http://www.oeamtc.at/verkehrsservice'));
        self::assertSame('https://fallback.example/', $url->orFallback('javascript:alert(1)', 'https://fallback.example/'));
        self::assertSame('https://fallback.example/', $url->orFallback('ftp://example.org/file', 'https://fallback.example/'));
        self::assertSame('https://fallback.example/', $url->orFallback('no url', 'https://fallback.example/'));
        self::assertNull($url->valid(null));
    }

    public function testStableIdDependsOnlyOnParts(): void
    {
        $id = new StableId();

        self::assertSame($id->fromParts('x', 'a', 'b'), $id->fromParts('x', 'a', 'b'));
        self::assertNotSame($id->fromParts('x', 'a', 'b'), $id->fromParts('x', 'ab'));
        self::assertMatchesRegularExpression('/^x:[0-9a-f]{16}$/', $id->fromParts('x', 'a'));
    }

    public function testParsesNumbersWithDecimalComma(): void
    {
        $numbers = new NumberParser();

        self::assertSame(5.13, $numbers->parse('5,13'));
        self::assertSame(5.13, $numbers->parse('5.13'));
        self::assertSame(410.0, $numbers->parse(410));
        self::assertNull($numbers->parse('—'));
        self::assertNull($numbers->parse(INF));
        self::assertSame(410, $numbers->parseInt('410'));
        self::assertNull($numbers->parseInt('4.5'));
    }
}
