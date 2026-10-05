<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Auth;

/**
 * Entry point of an authentication provider package (plugins/auth/<id>/plugin.php; ACCESS-AND-BRANDING A-D4): describes
 * the provider without any service and creates it with its settings from config.php (`auth.settings`). Needs a
 * constructor without parameters, because the core creates it from the generated registry.
 */
interface AuthProviderFactory
{
    public function describe(): AuthDescription;

    /** @throws \InvalidArgumentException for invalid settings; the core reports it as a configuration error */
    public function create(AuthEnvironment $environment): AuthProvider;
}
