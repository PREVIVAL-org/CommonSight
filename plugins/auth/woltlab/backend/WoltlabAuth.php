<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WoltlabAuth;

use CommonSight\Sdk\Auth\AuthProvider;
use CommonSight\Sdk\Auth\AuthRequest;
use CommonSight\Sdk\Auth\Community;

/**
 * Admits the logged-in members of a WoltLab forum: a session with a user, active within the last 60 days, whose user
 * is not banned. Logging out in the forum takes effect at once. Without a session cookie the database is not asked.
 */
final class WoltlabAuth implements AuthProvider
{
    /** As long as WoltLab keeps a session of a logged-in user (SessionHandler::USER_SESSION_LIFETIME). */
    public const SESSION_LIFETIME_SEC = 60 * 86400;

    public function __construct(
        private readonly SessionCookie $sessionCookie,
        private readonly ForumDatabase $database,
        private readonly string $cookieName,
        private readonly string $community,
        /** e.g. https://previval.org/ */
        private readonly string $forumUrl,
    ) {}

    public function admits(AuthRequest $request): bool
    {
        $sessionId = $this->sessionCookie->sessionId($request->cookies[$this->cookieName] ?? null);

        return $sessionId !== null && $this->database->isMember($sessionId, $request->now->timestamp - self::SESSION_LIFETIME_SEC);
    }

    /** Login with the return to the start page, and the registration of the forum. */
    public function community(AuthRequest $request): Community
    {
        return new Community(
            $this->community,
            $this->forumUrl . 'index.php?login/&url=' . rawurlencode($request->pageUrl),
            $this->forumUrl . 'index.php?register/',
        );
    }
}
