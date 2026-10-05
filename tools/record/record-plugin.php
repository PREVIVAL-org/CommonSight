<?php

declare(strict_types=1);

/**
 * Records the API responses of a plugin for its tests (concept: sources as plugins, P4; Architecture 12.4, F-15).
 *
 *   docker compose run --rm composer php ../tools/record/record-plugin.php <plugin-id> [--scope=AT] [--limit=25]
 *
 * Runs the plugin against the real API with a recording HTTP client and writes per scope
 * plugins/<group>/<id>/tests/responses/<scope>/index.json plus the bodies (001.json, ...). Large responses are trimmed to the
 * first N items. Secret values never reach the recording: requests are identified by their redacted URL. Works for every
 * plugin without an entry here.
 */

use CommonSight\Config\Config;
use CommonSight\Entry\PluginLoader;
use CommonSight\Infrastructure\Clock\SystemClock;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Http\CurlHandleFactory;
use CommonSight\Infrastructure\Http\CurlHttpClient;
use CommonSight\Infrastructure\Http\SourceHttpClient;
use CommonSight\Infrastructure\Recording\Cassette;
use CommonSight\Infrastructure\Recording\RecordingHttpClient;
use CommonSight\Infrastructure\Recording\ResponseTrimmer;
use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Model\Decoded;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\HttpClient;
use CommonSight\Sdk\Plugin\Secrets;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourceRun;

$backend = dirname(__DIR__, 2) . '/backend';
require $backend . '/vendor/autoload.php';

$arguments = Decoded::of($_SERVER['argv'] ?? [])->strings();
$id = null;
$onlyScope = null;
$limit = 25;
foreach (array_slice($arguments, 1) as $argument) {
    if (preg_match('/^--scope=(\w+)$/', $argument, $m) === 1) {
        $onlyScope = Scope::from($m[1]);
    } elseif (preg_match('/^--limit=(\d+)$/', $argument, $m) === 1) {
        $limit = (int) $m[1];
    } else {
        $id = $argument;
    }
}
if ($id === null) {
    fwrite(STDERR, "Usage: record-plugin.php <plugin-id> [--scope=AT] [--limit=25]\n");
    exit(2);
}

$cacheDir = sys_get_temp_dir() . '/commonsight-record';
$config = Config::fromArray(['paths' => ['data' => $cacheDir, 'state' => $cacheDir, 'cache' => $cacheDir, 'locks' => $cacheDir, 'logs' => $cacheDir]]);
$pluginsDir = PluginLoader::pluginsDir($backend);
$loader = new PluginLoader(new GeneratedData($backend . '/generated'), $pluginsDir, $config, new SafeXmlReader(), new SystemClock());
// The same limits as in operation, including those the plugins declare (e.g. 20 MB for the DWD).
$curl = new CurlHttpClient($loader->httpLimits(), new CurlHandleFactory(), microtime(true) + 300);
$trimmer = new ResponseTrimmer($limit);
/** @var array<string, RecordingHttpClient> $recorders */
$recorders = [];
/** @var array<string, string> $pluginDirs folder of each plugin, e.g. plugins/providers/pegelonline */
$pluginDirs = [];
$sources = $loader->sources(
    static function (SourceDescription $description, string $pluginDir) use ($curl, $trimmer, &$recorders, &$pluginDirs): HttpClient {
        $pluginDirs[$description->id] = $pluginDir;

        return $recorders[$description->id] = new RecordingHttpClient(new SourceHttpClient($curl, $description->id), $trimmer->trim(...));
    },
    // Secrets for the recording come from the environment, e.g. CS_SECRET_APIKEY=...; they are never written.
    static fn(SourceDescription $description): Secrets => new Secrets(array_filter(array_combine(
        $description->secrets,
        array_map(static fn(string $name): string => (string) getenv('CS_SECRET_' . strtoupper($name)), $description->secrets),
    ))),
);

$source = null;
foreach ($sources as $candidate) {
    if ($candidate->description->id === $id) {
        $source = $candidate;
    }
}
if ($source === null) {
    fwrite(STDERR, "No plugin $id in $pluginsDir (after adding a plugin: composer build-contract)\n");
    exit(1);
}

$now = UtcInstant::fromTimestamp(time());
foreach ($source->description->scopes as $scope) {
    if ($onlyScope !== null && $scope !== $onlyScope) {
        continue;
    }
    $plugin = $source->plugin();
    $recorder = $recorders[$id];
    $recorder->exchanges = [];
    $outcome = $plugin->fetch(new SourceRun($scope, $now));
    $dir = $pluginDirs[$id] . '/tests/responses/' . $scope->value;
    (new Cassette($now, $recorder->exchanges))->write($dir);
    printf(
        "  %s %s/%s: %d responses, %s\n",
        $outcome->succeeded() ? '✓' : '✗',
        $id,
        $scope->value,
        count($recorder->exchanges),
        $outcome->succeeded() ? count($outcome->items) . ' items' : 'failed: ' . $outcome->failure,
    );
}
