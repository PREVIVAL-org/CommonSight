<?php

declare(strict_types=1);

namespace CommonSight\Tests\Domain\Status;

use CommonSight\Domain\Status\ConditionalResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** 304 only for a matching ETag, even if Apache changed it while compressing (U-52). */
final class ConditionalResponseTest extends TestCase
{
    private const string ETAG = '"d74e8ed02a6000e91156"';

    /** @return iterable<string, array{string|null, bool}> */
    public static function candidates(): iterable
    {
        yield 'equal' => [self::ETAG, true];
        yield 'weak' => ['W/' . self::ETAG, true];
        yield 'changed by mod_deflate' => ['"d74e8ed02a6000e91156-gzip"', true];
        yield 'changed by mod_brotli' => ['"d74e8ed02a6000e91156-br"', true];
        yield 'in a list' => ['"other", "d74e8ed02a6000e91156-gzip"', true];
        yield 'asterisk' => ['*', true];
        yield 'different ETag' => ['"0000000000000000000a-gzip"', false];
        yield 'empty' => ['', false];
        yield 'missing' => [null, false];
    }

    #[DataProvider('candidates')]
    public function testMatchesTheEtagIgnoringCompressionSuffixes(?string $ifNoneMatch, bool $expected): void
    {
        self::assertSame($expected, (new ConditionalResponse())->isNotModified($ifNoneMatch, self::ETAG));
    }
}
