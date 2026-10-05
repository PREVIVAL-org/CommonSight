<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WoltlabAuth;

/**
 * Reads the session ID from the session cookie of WoltLab Suite 6: `<signature>-<base64>`, the Base64 part holds
 * version 1, 20 bytes session ID and one byte time step. The signature is not checked: the session ID is 160 random
 * bits, and whether it belongs to a logged-in member only the forum database tells.
 */
final class SessionCookie
{
    private const VERSION = 1;
    private const LENGTH = 22;

    /** @return string|null session ID as 40 hex digits, as in wcfN_user_session.sessionID; null for anything else */
    public function sessionId(?string $cookie): ?string
    {
        $parts = explode('-', (string) $cookie, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $value = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($value === false || strlen($value) !== self::LENGTH || ord($value[0]) !== self::VERSION) {
            return null;
        }

        return bin2hex(substr($value, 1, 20));
    }
}
