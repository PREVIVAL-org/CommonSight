<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk\Text;

use CommonSight\Sdk\Text\UtcTimeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** D-12, D-18: source timestamps from their actual time zone to UTC. */
final class UtcTimeParserTest extends TestCase
{
    /** @return iterable<string, array{mixed, string, ?string}> */
    public static function cases(): iterable
    {
        yield 'ISO with Z' => ['2026-09-28T10:15:00Z', 'Europe/Vienna', '2026-09-28T10:15:00Z'];
        yield 'ISO with offset' => ['2026-09-28T12:15:00+02:00', 'UTC', '2026-09-28T10:15:00Z'];
        yield 'LINDAS fixed offset +01:00' => ['2026-09-28T12:50:00+01:00', 'UTC', '2026-09-28T11:50:00Z'];
        yield 'Vienna local time, summer time' => ['2026-09-28T12:30:00', 'Europe/Vienna', '2026-09-28T10:30:00Z'];
        yield 'Vienna local time, winter time' => ['2026-12-01T12:30:00', 'Europe/Vienna', '2026-12-01T11:30:00Z'];
        yield 'repeated hour: earlier option' => ['2026-10-25T02:30:00', 'Europe/Vienna', '2026-10-25T00:30:00Z'];
        yield 'hour before the changeover' => ['2026-10-25T01:30:00', 'Europe/Vienna', '2026-10-24T23:30:00Z'];
        yield 'hour after the changeover' => ['2026-10-25T03:30:00', 'Europe/Vienna', '2026-10-25T02:30:00Z'];
        yield 'Kp without zone counts as UTC' => ['2026-09-21T03:00:00', 'UTC', '2026-09-21T03:00:00Z'];
        yield 'RFC 2822' => ['Mon, 28 Sep 2026 14:09:00 GMT', 'UTC', '2026-09-28T14:09:00Z'];
        yield 'RFC 2822 with offset' => ['Mon, 28 Sep 2026 16:09:00 +0200', 'UTC', '2026-09-28T14:09:00Z'];
        yield 'seconds since 1970' => [1790596800, 'UTC', '2026-09-28T12:00:00Z'];
        yield 'milliseconds (USGS)' => [1790596800123, 'UTC', '2026-09-28T12:00:00Z'];
        yield 'number as text' => ['1790596800', 'UTC', '2026-09-28T12:00:00Z'];
        yield 'empty' => ['', 'UTC', null];
        yield 'null' => [null, 'UTC', null];
        yield 'relative' => ['tomorrow', 'UTC', null];
        yield 'nonsense' => ['2026-13-45T99:00:00', 'UTC', null];
        yield 'Array' => [['x'], 'UTC', null];
    }

    #[DataProvider('cases')]
    public function testConvertsToUtc(mixed $value, string $zone, ?string $expected): void
    {
        $result = (new UtcTimeParser())->parse($value, new \DateTimeZone($zone));

        self::assertSame($expected, $result?->toIso());
    }

    public function testIgnoresPhpDefaultTimezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            self::assertSame('2026-09-28T10:00:00Z', (new UtcTimeParser())->parseUtc('2026-09-28T10:00:00')?->toIso());
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
