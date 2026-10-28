<?php

declare(strict_types=1);

// Situation endpoint (concept: situation level). The webroot only contains api/situation.php, which includes this file.

use CommonSight\Config\ConfigError;
use CommonSight\Entry\ConfigLoader;
use CommonSight\Entry\StatusEndpointFactory;
use CommonSight\Infrastructure\Contract\GeneratedData;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $config = (new ConfigLoader())->load(null, dirname(__DIR__));
    (new StatusEndpointFactory($config, new GeneratedData(dirname(__DIR__) . '/generated')))->situationEndpoint()->handle($_GET, $_SERVER);
} catch (ConfigError $e) {
    error_log('[commonsight] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Configuration error\n";
    }
}
