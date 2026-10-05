<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WoltlabAuth\Tests;

use CommonSight\Plugin\WoltlabAuth\SessionCookie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The session ID in the session cookie of WoltLab Suite 6. */
final class SessionCookieTest extends TestCase
{
    public const SESSION = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';

    public static function cookie(string $session = self::SESSION, int $version = 1): string
    {
        return hash('sha256', 'x') . '-' . base64_encode(chr($version) . (string) hex2bin($session) . chr(0));
    }

    public function testReadsTheSessionIdAsHex(): void
    {
        self::assertSame(self::SESSION, (new SessionCookie())->sessionId(self::cookie()));
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidCookies(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'without signature' => [base64_encode(chr(1) . str_repeat('a', 21))];
        yield 'no base64' => ['abc-%%%'];
        yield 'too short' => ['abc-' . base64_encode(chr(1) . 'short')];
        yield 'other version' => [self::cookie(self::SESSION, 2)];
    }

    #[DataProvider('invalidCookies')]
    public function testAnythingElseHasNoSession(?string $cookie): void
    {
        self::assertNull((new SessionCookie())->sessionId($cookie));
    }
}
