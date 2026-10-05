<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WoltlabAuth;

use CommonSight\Model\Decoded;
use CommonSight\Sdk\Auth\AuthDescription;
use CommonSight\Sdk\Auth\AuthEnvironment;
use CommonSight\Sdk\Auth\AuthProvider;
use CommonSight\Sdk\Auth\AuthProviderFactory;

/**
 * Members of a WoltLab Suite forum (WoltLab 6). Settings (`auth.settings` in config.php):
 * - `cookie`: name of the forum's session cookie, e.g. wsc_1d3758_user_session
 * - `community`: name shown on the members card, e.g. PREVIVAL.org
 * - `forumUrl`: address of the forum, for login and registration, e.g. https://previval.org/
 * - `database`: own access data (`host`, `port`, `name`, `user`, `password`, optionally `instance` for wcfN_), best a
 *   read-only user with SELECT on wcfN_user_session and wcfN_user; or instead
 * - `forumConfig`: path of the forum's config.inc.php, read for each question (works with the forum's full rights).
 */
final class WoltlabAuthFactory implements AuthProviderFactory
{
    private const KEYS = ['cookie', 'community', 'forumUrl', 'database', 'forumConfig'];

    public function describe(): AuthDescription
    {
        return new AuthDescription('woltlab', 'WoltLab Suite');
    }

    public function create(AuthEnvironment $environment): AuthProvider
    {
        $settings = $environment->settings;
        $unknown = array_diff(array_keys($settings->entries()), self::KEYS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf('unknown %s (known: %s)', implode(', ', $unknown), implode(', ', self::KEYS)));
        }
        $cookie = $settings->get('cookie')->string() ?? '';
        if (preg_match('/^[A-Za-z0-9_]+$/', $cookie) !== 1) {
            throw new \InvalidArgumentException('cookie: the name of the forum\'s session cookie, e.g. wsc_1d3758_user_session');
        }
        $community = trim($settings->get('community')->string() ?? '');
        $forumUrl = $settings->get('forumUrl')->string() ?? '';
        if ($community === '' || preg_match('#^https?://[^\s]+/$#i', $forumUrl) !== 1) {
            throw new \InvalidArgumentException('community (name of the forum) and forumUrl (its address ending with /) are needed');
        }

        return new WoltlabAuth(new SessionCookie(), $this->database($settings), $cookie, $community, $forumUrl);
    }

    private function database(Decoded $settings): ForumDatabase
    {
        $access = $settings->get('database');
        $forumConfig = $settings->get('forumConfig')->string() ?? '';
        if ($access->isNull() === ($forumConfig === '')) {
            throw new \InvalidArgumentException('either database (own access data) or forumConfig (path of config.inc.php)');
        }
        if ($forumConfig !== '') {
            return ForumDatabase::fromForumConfig($forumConfig);
        }

        return ForumDatabase::fromAccess(
            $access->get('host')->string() ?? '',
            $access->get('port')->int() ?? 0,
            $access->get('name')->string() ?? '',
            $access->get('user')->string() ?? '',
            $access->get('password')->string() ?? '',
            $access->get('instance')->int() ?? 1,
        );
    }
}
