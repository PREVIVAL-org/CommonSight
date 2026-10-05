<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WoltlabAuth\Tests;

use CommonSight\Model\Decoded;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\WoltlabAuth\ForumDatabase;
use CommonSight\Plugin\WoltlabAuth\SessionCookie;
use CommonSight\Plugin\WoltlabAuth\WoltlabAuth;
use CommonSight\Plugin\WoltlabAuth\WoltlabAuthFactory;
use CommonSight\Sdk\Auth\AuthEnvironment;
use CommonSight\Sdk\Auth\AuthRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Members of a WoltLab forum: logged in, active within 60 days, not banned. */
final class WoltlabAuthTest extends TestCase
{
    private const NOW = 1_790_000_000;
    private const COOKIE = 'wsc_1_user_session';

    /** A forum database in memory: a member, a banned user, a guest and a session older than 60 days. */
    private static function database(int $instance = 1): ForumDatabase
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE wcf{$instance}_user_session (sessionID TEXT PRIMARY KEY, userID INTEGER NULL, lastActivityTime INTEGER)");
        $pdo->exec("CREATE TABLE wcf{$instance}_user (userID INTEGER PRIMARY KEY, banned INTEGER)");
        $pdo->exec("INSERT INTO wcf{$instance}_user VALUES (1, 0), (2, 1)");
        $old = self::NOW - WoltlabAuth::SESSION_LIFETIME_SEC - 10;
        $pdo->exec(sprintf("INSERT INTO wcf{$instance}_user_session VALUES ('%s', 1, %d), ('%s', 2, %d), ('%s', NULL, %d), ('%s', 1, %d)", str_repeat('a', 40), self::NOW, str_repeat('b', 40), self::NOW, str_repeat('c', 40), self::NOW, str_repeat('d', 40), $old));

        return new ForumDatabase(static fn(): array => [$pdo, $instance]);
    }

    private static function request(?string $session): AuthRequest
    {
        $cookies = $session === null ? [] : [self::COOKIE => SessionCookieTest::cookie($session)];

        return new AuthRequest($cookies, 'https://commonsight.example.org/', UtcInstant::fromTimestamp(self::NOW));
    }

    private static function auth(ForumDatabase $database): WoltlabAuth
    {
        return new WoltlabAuth(new SessionCookie(), $database, self::COOKIE, 'PREVIVAL.org', 'https://previval.org/');
    }

    public function testOnlyActiveSessionsOfMembersThatAreNotBanned(): void
    {
        $auth = self::auth(self::database());

        self::assertTrue($auth->admits(self::request(str_repeat('a', 40))));
        self::assertFalse($auth->admits(self::request(str_repeat('b', 40))), 'banned');
        self::assertFalse($auth->admits(self::request(str_repeat('c', 40))), 'guest or logged out');
        self::assertFalse($auth->admits(self::request(str_repeat('d', 40))), 'older than 60 days');
        self::assertFalse($auth->admits(self::request(str_repeat('e', 40))), 'unknown session');
        self::assertFalse($auth->admits(self::request(null)), 'no cookie');
    }

    public function testUsesTheInstanceNumberOfTheTablePrefix(): void
    {
        self::assertTrue(self::auth(self::database(3))->admits(self::request(str_repeat('a', 40))));
    }

    public function testWithoutACookieTheDatabaseIsNotAsked(): void
    {
        $auth = self::auth(new ForumDatabase(static fn(): array => throw new \PDOException('must not connect')));

        self::assertFalse($auth->admits(self::request(null)));
    }

    public function testAnUnreachableDatabaseIsAnError(): void
    {
        $auth = self::auth(new ForumDatabase(static fn(): array => throw new \PDOException('connection refused')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('forum database not reachable');
        $auth->admits(self::request(str_repeat('a', 40)));
    }

    public function testAnUnreadableForumConfigurationIsAnErrorOfTheQuestionNotOfTheStart(): void
    {
        $auth = self::auth(ForumDatabase::fromForumConfig('/nonexistent/config.inc.php'));

        $this->expectExceptionMessage('not readable');
        $auth->admits(self::request(str_repeat('a', 40)));
    }

    public function testNamesTheForumWithLoginBackToTheStartPage(): void
    {
        $community = self::auth(self::database())->community(self::request(null));

        self::assertSame('PREVIVAL.org', $community->name);
        self::assertSame('https://previval.org/index.php?login/&url=https%3A%2F%2Fcommonsight.example.org%2F', $community->loginUrl);
        self::assertSame('https://previval.org/index.php?register/', $community->registerUrl);
    }

    public function testCreatesTheProviderFromItsSettings(): void
    {
        $settings = ['cookie' => self::COOKIE, 'community' => 'PREVIVAL.org', 'forumUrl' => 'https://previval.org/', 'database' => ['host' => 'db', 'name' => 'forum', 'user' => 'cs_read', 'password' => 'x']];

        self::assertInstanceOf(WoltlabAuth::class, (new WoltlabAuthFactory())->create(new AuthEnvironment(Decoded::of($settings))));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        $valid = ['cookie' => self::COOKIE, 'community' => 'PREVIVAL.org', 'forumUrl' => 'https://previval.org/', 'forumConfig' => '/forum/config.inc.php'];
        yield 'misspelled key' => [$valid + ['cookies' => 'x']];
        yield 'cookie with spaces' => [['cookie' => 'a b'] + $valid];
        yield 'without community' => [['community' => ''] + $valid];
        yield 'forum without slash' => [['forumUrl' => 'https://previval.org'] + $valid];
        yield 'neither database nor config' => [array_diff_key($valid, ['forumConfig' => 0])];
        yield 'both database and config' => [$valid + ['database' => ['host' => 'db', 'name' => 'forum', 'user' => 'u', 'password' => 'x']]];
        yield 'database without user' => [array_diff_key($valid, ['forumConfig' => 0]) + ['database' => ['host' => 'db', 'name' => 'forum']]];
    }

    /** @param array<string, mixed> $settings */
    #[DataProvider('invalidSettings')]
    public function testRefusesInvalidSettings(array $settings): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new WoltlabAuthFactory())->create(new AuthEnvironment(Decoded::of($settings)));
    }
}
