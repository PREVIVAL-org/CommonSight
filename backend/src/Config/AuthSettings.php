<?php

declare(strict_types=1);

namespace CommonSight\Config;

/**
 * The authentication provider of the start page (ACCESS-AND-BRANDING A-D4): its id (a package in plugins/auth/) and its
 * settings, validated by the provider when it is created. Without it the start page is open to everyone.
 */
final readonly class AuthSettings
{
    /** @param array<mixed> $settings */
    public function __construct(public string $provider, public array $settings = []) {}
}
