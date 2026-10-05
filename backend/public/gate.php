<?php

declare(strict_types=1);

// Start page through the authentication provider (ACCESS-AND-BRANDING A-D3). The webroot has gate.php, which includes
// this file; the .htaccess sends / and /index.html here. Closed on every error: the element then shows the members card.

use CommonSight\Application\StartPageGate;
use CommonSight\Config\Config;
use CommonSight\Entry\AuthLoader;
use CommonSight\Entry\ConfigLoader;
use CommonSight\Entry\StartPageBranding;
use CommonSight\Entry\StartPageEndpoint;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Log\JsonLineLogger;

require dirname(__DIR__) . '/vendor/autoload.php';

$script = $_SERVER['SCRIPT_FILENAME'] ?? '';
$webroot = dirname(is_string($script) ? $script : '');
$index = $webroot . '/index.html';
$scheme = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https' ? 'https' : 'http';
$host = is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'localhost';
$path = strtok(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', '?');
$pageUrl = $scheme . '://' . $host . (is_string($path) ? $path : '/');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-cache');
header('Vary: Cookie');

// Logo and favicon of the installation (custom/branding.json); a broken file keeps the defaults and is reported.
$brandingError = null;
try {
    $branding = StartPageBranding::fromFile($webroot . '/custom/branding.json');
} catch (\InvalidArgumentException $e) {
    $branding = StartPageBranding::none();
    $brandingError = $e->getMessage();
}

/** @var Config|null $config */
$config = null;
try {
    $config = (new ConfigLoader())->load(null, dirname(__DIR__));
    $log = new JsonLineLogger($config->paths->logs . '/fetcher.log', 'gate');
    if ($brandingError !== null) {
        $log->log('warning', 'branding.invalid', ['reason' => $brandingError]);
    }
    $provider = (new AuthLoader(new GeneratedData(dirname(__DIR__) . '/generated')))->provider($config->auth);
    echo (new StartPageEndpoint(new StartPageGate($provider, $log), $index, $branding))->respond($_COOKIE, $pageUrl);
} catch (\Throwable $e) {
    if ($config instanceof Config) {
        (new JsonLineLogger($config->paths->logs . '/fetcher.log', 'gate'))->log('error', 'gate.exception', ['class' => $e::class, 'message' => $e->getMessage()]);
    }
    error_log('[commonsight] gate: ' . $e->getMessage());
    echo StartPageEndpoint::closed($index, $branding);
}
