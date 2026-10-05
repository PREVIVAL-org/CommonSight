<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;

/** Finds and loads config.php: path from the call, environment variable CS_CONFIG or config.php two levels above the code, next to releases/ (e.g. <home>/<app>/config.php). */
final class ConfigLoader
{
    public function load(?string $explicitPath, string $releaseDir): Config
    {
        $path = $explicitPath ?? (getenv('CS_CONFIG') ?: dirname($releaseDir, 2) . '/config.php');
        if (!is_file($path)) {
            throw new ConfigError('Configuration not found: ' . $path);
        }
        $raw = require $path;
        if (!is_array($raw)) {
            throw new ConfigError('config.php must return an array: ' . $path);
        }

        return Config::fromArray($raw);
    }
}
