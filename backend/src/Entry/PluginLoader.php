<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Config\HttpLimits;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Http\SourceHttpClient;
use CommonSight\Infrastructure\Plugin\FilePluginData;
use CommonSight\Infrastructure\Plugin\FilePluginStore;
use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Infrastructure\Storage\AtomicFile;
use CommonSight\Port\Clock;
use CommonSight\Port\HttpClient;
use CommonSight\Port\PluginStore;
use CommonSight\Port\XmlEntryReader;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\Secrets;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;

/**
 * Creates the plugins found at build time (generated/plugins.php) with their descriptions; each plugin gets its own
 * environment when it is first fetched (concept: sources as plugins, 4.2, D1). Who creates the plugins decides which
 * HTTP client and which secrets a plugin gets: the real ones in operation, recordings in tests and tools.
 */
final class PluginLoader
{
    /** @var array<string, array{SourcePluginFactory, SourceDescription, string}>|null id -> factory, description, folder */
    private ?array $plugins = null;

    public function __construct(
        private readonly GeneratedData $data,
        private readonly string $pluginsDir,
        private readonly Config $config,
        private readonly XmlEntryReader $xml,
        private readonly Clock $clock,
    ) {}

    /** The plugins' folder: next to the code in a release (app/plugins), next to backend/ in the repository. */
    public static function pluginsDir(string $backendDir): string
    {
        return is_dir($backendDir . '/plugins') ? $backendDir . '/plugins' : dirname($backendDir) . '/plugins';
    }

    /** @return list<SourceDescription> */
    public function descriptions(): array
    {
        return array_values(array_map(static fn(array $plugin): SourceDescription => $plugin[1], $this->plugins()));
    }

    /** The HTTP limits of the configuration plus the limits each plugin declares for itself (for every HTTP client). */
    public function httpLimits(): HttpLimits
    {
        $limits = $this->config->http;
        foreach ($this->descriptions() as $description) {
            $limits = $limits->withSourceDefaults($description->id, $description->http->timeoutSec, $description->http->maxBytes);
        }

        return $limits;
    }

    /**
     * In operation: every plugin with the shared HTTP client, marked with its source id.
     *
     * @return list<RegisteredSource>
     */
    public function operational(HttpClient $http): array
    {
        return $this->sources(
            static fn(SourceDescription $description): HttpClient => new SourceHttpClient($http, $description->id),
            fn(SourceDescription $description): Secrets => new Secrets(array_intersect_key(
                ($this->config->sources[$description->id] ?? null)->secrets ?? [],
                array_flip($description->secrets),
            )),
        );
    }

    /**
     * @param \Closure(SourceDescription, string): HttpClient $httpFor client of a plugin, by description and folder
     * @param \Closure(SourceDescription): Secrets $secretsFor
     * @param (\Closure(SourceDescription): PluginStore)|null $storeFor store of a plugin; by default its folder in cache/plugins/
     * @return list<RegisteredSource>
     */
    public function sources(\Closure $httpFor, \Closure $secretsFor, ?\Closure $storeFor = null): array
    {
        $storeFor ??= fn(SourceDescription $description): PluginStore => new FilePluginStore($this->config->paths->cache . '/plugins/' . $description->id, new AtomicFile(), $this->clock);
        $sources = [];
        foreach ($this->plugins() as [$factory, $description, $dir]) {
            $secrets = $secretsFor($description);
            $sources[] = new RegisteredSource(
                $description,
                fn(): SourcePlugin => $factory->create(new PluginEnvironment(
                    $httpFor($description, $this->pluginsDir . '/' . $dir),
                    $storeFor($description),
                    $secrets,
                    $this->xml,
                    new FilePluginData($this->pluginsDir . '/' . $dir . '/data', $this->data),
                )),
                ($this->config->sources[$description->id] ?? null)?->order,
                array_values(array_filter($description->secrets, static fn(string $name): bool => !$secrets->has($name))),
            );
        }

        return $sources;
    }

    /** @return array<string, array{SourcePluginFactory, SourceDescription, string}> */
    private function plugins(): array
    {
        if ($this->plugins === null) {
            $plugins = [];
            foreach ($this->data->plugins() as $id => $plugin) {
                if ($plugin['kind'] !== PluginDiscovery::SOURCE) {
                    continue;
                }
                $factory = new ($plugin['factory'])();
                if (!$factory instanceof SourcePluginFactory) {
                    throw new \RuntimeException('Plugin ' . $id . ': not a ' . SourcePluginFactory::class . ' (rebuild with bin/build-contract.php)');
                }
                $plugins[$id] = [$factory, $factory->describe(), $plugin['dir']];
            }
            // Settings for a source that does not exist (a typo, a plugin not installed) would silently be ignored.
            // Checked before keeping the list, so that every later call fails the same way.
            $this->refuseUnknown('sources', array_keys($this->config->sources), array_keys($plugins));
            $this->refuseUnknown('http.maxBytesBySource', array_keys($this->config->http->maxBytesBySource), array_keys($plugins));
            $this->refuseUnknown('http.requestTimeoutBySource', array_keys($this->config->http->requestTimeoutBySource), array_keys($plugins));
            $this->plugins = $plugins;
        }

        return $this->plugins;
    }

    /**
     * @param list<string> $configured source ids named in a section of the configuration
     * @param list<string> $known
     * @throws ConfigError
     */
    private function refuseUnknown(string $section, array $configured, array $known): void
    {
        $unknown = array_diff($configured, $known);
        if ($unknown !== []) {
            throw new ConfigError(sprintf('%s: unknown source %s (known: %s)', $section, implode(', ', $unknown), implode(', ', $known)));
        }
    }
}
