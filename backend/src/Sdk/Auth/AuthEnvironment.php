<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Auth;

use CommonSight\Model\Decoded;

/** What the core lends a provider: its settings from config.php (`auth.settings`), validated by the provider. */
final readonly class AuthEnvironment
{
    public function __construct(public Decoded $settings) {}
}
