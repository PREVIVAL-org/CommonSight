<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\AuthSettings;
use CommonSight\Config\ConfigError;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Model\Decoded;
use CommonSight\Sdk\Auth\AuthEnvironment;
use CommonSight\Sdk\Auth\AuthProvider;
use CommonSight\Sdk\Auth\AuthProviderFactory;

/** Creates the authentication provider that config.php names (`auth.provider`) with its settings (A-D4). */
final class AuthLoader
{
    public function __construct(private readonly GeneratedData $data) {}

    /** @throws ConfigError for an unknown provider or invalid settings */
    public function provider(?AuthSettings $settings): ?AuthProvider
    {
        if ($settings === null) {
            return null;
        }
        $known = array_keys(array_filter($this->data->plugins(), static fn(array $p): bool => $p['kind'] === PluginDiscovery::AUTH));
        $plugin = in_array($settings->provider, $known, true) ? $this->data->plugins()[$settings->provider] : null;
        if ($plugin === null) {
            throw new ConfigError(sprintf('auth.provider: unknown provider %s (known: %s)', $settings->provider, $known === [] ? 'none' : implode(', ', $known)));
        }
        $factory = new ($plugin['factory'])();
        if (!$factory instanceof AuthProviderFactory) {
            throw new \RuntimeException('Plugin ' . $settings->provider . ': not a ' . AuthProviderFactory::class . ' (rebuild with bin/build-contract.php)');
        }
        try {
            return $factory->create(new AuthEnvironment(Decoded::of($settings->settings)));
        } catch (\InvalidArgumentException $e) {
            throw new ConfigError('auth.settings: ' . $e->getMessage(), 0, $e);
        }
    }
}
