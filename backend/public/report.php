<?php

declare(strict_types=1);

// Error reports of the browsers (Architecture 6.6). The webroot only contains api/report.php, which includes this file.
// Only from visitors the start page admits; closed on every error, and nothing about the visitor goes to fetcher.log.

use CommonSight\Application\ClientReportIntake;
use CommonSight\Application\StartPageGate;
use CommonSight\Domain\Report\ClientReportParser;
use CommonSight\Entry\AuthLoader;
use CommonSight\Entry\ClientReportEndpoint;
use CommonSight\Entry\ConfigLoader;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Log\ClientReportFile;
use CommonSight\Infrastructure\Log\JsonLineLogger;

require dirname(__DIR__) . '/vendor/autoload.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

try {
    $config = (new ConfigLoader())->load(null, dirname(__DIR__));
    $intake = null;
    if ($config->clientReports->enabled) {
        $provider = (new AuthLoader(new GeneratedData(dirname(__DIR__) . '/generated')))->provider($config->auth);
        $gate = new StartPageGate($provider, new JsonLineLogger($config->paths->logs . '/fetcher.log', 'report'));
        $intake = new ClientReportIntake($gate, new ClientReportParser(), new ClientReportFile($config->paths->logs));
    }
    $body = (string) file_get_contents('php://input', false, null, 0, ClientReportEndpoint::MAX_BYTES + 1);
    $status = (new ClientReportEndpoint($intake))->answer($_SERVER, $_COOKIE, $body);
    if ($status === 405) {
        header('Allow: POST');
    }
    http_response_code($status);
} catch (\Throwable $e) {
    error_log('[commonsight] report: ' . $e->getMessage());
    http_response_code(500);
}
