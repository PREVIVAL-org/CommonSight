<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Auth;

/** The community whose members are admitted, as shown on the members card: its name and the way to it. */
final readonly class Community
{
    public function __construct(
        /** e.g. "PREVIVAL.org" */
        public string $name,
        public ?string $loginUrl = null,
        public ?string $registerUrl = null,
    ) {
        foreach ([$loginUrl, $registerUrl] as $url) {
            if ($url !== null && preg_match('#^https?://#i', $url) !== 1) {
                throw new \InvalidArgumentException('Community link must be http(s): ' . $url);
            }
        }
    }
}
