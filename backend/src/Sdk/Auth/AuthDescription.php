<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Auth;

/** Who an authentication provider is: its id (the folder name, the value of `auth.provider`) and its name. */
final readonly class AuthDescription
{
    public function __construct(
        public string $id,
        /** e.g. "WoltLab Suite" */
        public string $name,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{1,30}$/', $id) !== 1 || trim($name) === '') {
            throw new \InvalidArgumentException('Invalid auth provider: ' . $id);
        }
    }
}
