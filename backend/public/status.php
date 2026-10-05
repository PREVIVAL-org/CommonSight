<?php

declare(strict_types=1);

// Status endpoint (Architecture 6.1 to 6.3). The webroot only contains api/status.php, which includes this file.

use CommonSight\Config\ConfigError;
use CommonSight\Entry\ConfigLoader;
use CommonSight\Entry\StatusEndpointFactory;
use CommonSight\Infrastructure\Contract\GeneratedData;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    // Sources and layers are checked against the configuration only when they are needed (unknown ids, settings): also
    // while the request is handled, e.g. when it runs the fallback.
    $config = (new ConfigLoader())->load(null, dirname(__DIR__));
    (new StatusEndpointFactory($config, new GeneratedData(dirname(__DIR__) . '/generated')))->endpoint()->handle($_GET, $_SERVER);
} catch (ConfigError $e) {
    error_log('[commonsight] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Configuration error\n";
    }
}
