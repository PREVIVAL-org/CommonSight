<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WoltlabAuth;

/**
 * Asks the database of the forum whether a session belongs to a member: one read by primary key, joined with the user
 * for the ban. The connection is opened only on the first question, so a page without a session cookie needs none.
 */
final class ForumDatabase
{
    /** @var array{\PDO, int}|null the open connection and the WoltLab instance number (the N of the table prefix wcfN_) */
    private ?array $connection = null;

    /** @param \Closure(): array{\PDO, int} $connect opens the connection and names the instance number */
    public function __construct(private readonly \Closure $connect) {}

    /**
     * Own access data (settings `database`), best a user that may only read the two tables.
     *
     * @throws \InvalidArgumentException for incomplete access data
     */
    public static function fromAccess(string $host, int $port, string $name, string $user, string $password, int $instance = 1): self
    {
        if ($host === '' || $name === '' || $user === '' || $port < 0 || $instance < 1) {
            throw new \InvalidArgumentException('database: host, name and user are needed');
        }
        $dsn = sprintf('mysql:host=%s;%sdbname=%s;charset=utf8mb4', $host, $port > 0 ? 'port=' . $port . ';' : '', $name);

        return new self(static fn(): array => [new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 3,
        ]), $instance]);
    }

    /**
     * The access data of the forum from its config.inc.php ($dbHost, $dbPort, $dbUser, $dbPassword, $dbName, WCF_N),
     * read in a scope of its own when the first question comes; nothing of it is kept apart from the connection.
     */
    public static function fromForumConfig(string $configFile): self
    {
        return new self(static function () use ($configFile): array {
            if (!is_file($configFile) || !is_readable($configFile)) {
                throw new \PDOException('configuration of the forum not readable: ' . $configFile);
            }
            /** @var array<string, mixed> $settings */
            $settings = (static function (string $file): array {
                require $file;

                return get_defined_vars();
            })($configFile);
            $text = static fn(string $key): string => is_scalar($settings[$key] ?? null) ? (string) $settings[$key] : '';
            $number = defined('WCF_N') ? constant('WCF_N') : 1;

            return (self::fromAccess($text('dbHost'), (int) $text('dbPort'), $text('dbName'), $text('dbUser'), $text('dbPassword'), is_int($number) ? $number : 1)->connect)();
        });
    }

    /** @throws \RuntimeException if the database cannot be asked */
    public function isMember(string $sessionId, int $activeSince): bool
    {
        try {
            [$pdo, $instance] = $this->connection ??= ($this->connect)();
            $statement = $pdo->prepare(sprintf(
                'SELECT u.banned FROM wcf%1$d_user_session s JOIN wcf%1$d_user u ON u.userID = s.userID WHERE s.sessionID = ? AND s.lastActivityTime >= ?',
                $instance,
            ));
            $statement->execute([$sessionId, $activeSince]);
            $banned = $statement->fetchColumn();
        } catch (\PDOException | \InvalidArgumentException $e) {
            throw new \RuntimeException('forum database not reachable: ' . $e->getMessage(), 0, $e);
        }

        return $banned !== false && (int) $banned === 0;
    }
}
