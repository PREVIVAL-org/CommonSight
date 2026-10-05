<?php

declare(strict_types=1);

namespace CommonSight\Tests\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Entry\EnvironmentCheck;
use CommonSight\Entry\FetcherFactory;
use CommonSight\Entry\LayerLoader;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/** F-01, F-19, Architecture 5.1; concept "sources as plugins", 6.1. */
final class CompositionTest extends TestCase
{
    public function testRegistersEveryLayerForEveryScope(): void
    {
        $factory = new FetcherFactory($this->config(), Fixtures::generated(), 'test', microtime(true));
        $catalog = $factory->layers()->catalog($factory->sourceDescriptions());

        // Every layer of the registry in every scope it has: three countries or global, plus the border zone where it has one.
        $expected = array_sum(array_map(static fn($meta): int => count($meta->scopes()), Fixtures::generated()->layerMetas()));
        self::assertGreaterThan(0, $expected);
        self::assertCount($expected, $catalog->keys());
        foreach ($catalog->keys() as $key) {
            [$scope, $layer] = explode('/', $key);
            $definition = $catalog->get(\CommonSight\Model\Value\LayerId::from($layer), \CommonSight\Model\Value\Scope::from($scope));
            self::assertSame($layer, $definition->layer->value);
        }
    }

    /** Concept "sources as plugins", 6.1: the warnings run in the fast lane, everything else in heavy, slow is empty. */
    public function testSourcesChooseTheirLanes(): void
    {
        $plans = (new FetcherFactory($this->config(), Fixtures::generated(), 'test', microtime(true)))->plans();
        $lanes = [];
        foreach ($plans->all() as $plan) {
            $lanes[$plan->lane][$plan->source->description->layer] = true;
        }

        self::assertSame(['warnings'], array_keys($lanes['fast']));
        self::assertNotContains('warnings', array_keys($lanes['heavy']));
        self::assertArrayNotHasKey('slow', $lanes);
        self::assertSame(['CH-traffic'], array_map(static fn($t): string => $t->name(), $plans->targetsWithoutSources()), 'until a plugin brings Swiss traffic data');
    }

    /** F-19: no source is fetched more often than it has new data or its terms allow. */
    public function testEffectiveIntervalsRespectUpdateRatesAndTerms(): void
    {
        $factory = new FetcherFactory($this->config(), Fixtures::generated(), 'test', microtime(true));
        foreach ($factory->plans()->all() as $plan) {
            $schedule = $plan->source->description->schedule;
            self::assertGreaterThanOrEqual(max($schedule->updateRateSec, $schedule->termsMinIntervalSec ?? 0), $plan->intervalSec, $plan->key());
        }
        $results = (new EnvironmentCheck())->run($this->config(), Fixtures::generated(), $factory);
        $sources = count(array_filter(Fixtures::generated()->plugins(), static fn(array $p): bool => $p['kind'] === 'source'));
        self::assertSame(['Source plugins: ' . $sources], array_values(array_filter(array_column($results, 1), static fn(string $r): bool => str_starts_with($r, 'Source plugins'))));
        self::assertCount(3, array_filter(array_column($results, 1), static fn(string $r): bool => str_starts_with($r, 'Lane ')));
        self::assertContains([true, 'Layer radiation: warningUSvH 0.3 (default), highUSvH 1.0 (default) (layers.radiation.settings)'], $results, 'L-D5: settings with their defaults');
    }

    public function testConfiguredLaneAndIntervalAreChecked(): void
    {
        $dir = sys_get_temp_dir() . '/cs-composition';
        $paths = ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir];
        $plans = (new FetcherFactory(Config::fromArray(['paths' => $paths, 'sources' => ['usgs' => ['lane' => 'slow', 'intervalSec' => 900]]]), Fixtures::generated(), 'test', microtime(true)))->plans();
        $usgs = array_values(array_filter($plans->all(), static fn($p): bool => $p->id() === 'usgs'));
        self::assertSame(['slow', 900], [$usgs[0]->lane, $usgs[0]->intervalSec]);

        foreach ([['lane' => 'nightly'], ['intervalSec' => 30]] as $invalid) {
            try {
                (new FetcherFactory(Config::fromArray(['paths' => $paths, 'sources' => ['usgs' => $invalid]]), Fixtures::generated(), 'test', microtime(true)))->plans();
                self::fail('accepted: ' . json_encode($invalid));
            } catch (ConfigError $e) {
                self::assertStringContainsString('usgs', $e->getMessage());
            }
        }
    }

    /** L-D5: a layer validates its settings from config.php; an invalid one is a configuration error at the start. */
    public function testLayerSettingsAreValidatedWhenTheLayersAreCreated(): void
    {
        $dir = sys_get_temp_dir() . '/cs-composition';
        $paths = ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir];
        foreach ([
            'swapped thresholds' => [['radiation' => ['settings' => ['warningUSvH' => 1.0, 'highUSvH' => 0.3]]], 'layers.radiation.settings'],
            'unknown setting' => [['radiation' => ['settings' => ['redUSvH' => 2.0]]], 'layers.radiation.settings: unknown redUSvH'],
            'not a number' => [['radiation' => ['settings' => ['highUSvH' => 'viel']]], 'layers.radiation.settings'],
            'misspelled layer' => [['radiaton' => ['settings' => ['highUSvH' => 2.0]]], 'layers: unknown layer radiaton'],
        ] as $case => [$layers, $message]) {
            $factory = new FetcherFactory(Config::fromArray(['paths' => $paths, 'layers' => $layers]), Fixtures::generated(), 'test', microtime(true));
            try {
                $factory->layers()->catalog([]);
                self::fail('accepted: ' . $case);
            } catch (ConfigError $e) {
                self::assertStringStartsWith($message, $e->getMessage(), $case);
            }
        }
    }

    /** The check shows a changed setting with its default, an unchanged one as the default (L-D5). */
    public function testTheCheckShowsChangedLayerSettings(): void
    {
        $dir = sys_get_temp_dir() . '/cs-composition';
        $config = Config::fromArray(['paths' => ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir], 'layers' => ['radiation' => ['settings' => ['warningUSvH' => 0.3, 'highUSvH' => 2]]]]);
        $results = (new EnvironmentCheck())->run($config, Fixtures::generated(), new FetcherFactory($config, Fixtures::generated(), 'test', microtime(true)));

        self::assertContains([true, 'Layer radiation: warningUSvH 0.3 (default), highUSvH 2.0 (default 1.0) (layers.radiation.settings)'], $results);
    }

    /** An invalid layer setting fails the check like it stops the fetcher (L-D5). */
    public function testTheCheckReportsInvalidLayerSettings(): void
    {
        $dir = sys_get_temp_dir() . '/cs-composition';
        $config = Config::fromArray(['paths' => ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir], 'layers' => ['radiation' => ['settings' => ['highUSvH' => 'viel']]]]);
        $results = (new EnvironmentCheck())->run($config, Fixtures::generated(), new FetcherFactory($config, Fixtures::generated(), 'test', microtime(true)));

        $failed = array_values(array_filter($results, static fn(array $r): bool => !$r[0]));
        self::assertCount(1, $failed);
        self::assertStringStartsWith('layers.radiation.settings', $failed[0][1]);
    }

    /** A layer package with a broken data file fails only its own layer, and the check names it. */
    public function testABrokenLayerPackageFailsOnlyItsLayer(): void
    {
        $plugins = TempDir::create('broken-layer');
        try {
            foreach (Fixtures::generated()->plugins() as $id => $plugin) {
                $names = dirname(Fixtures::backendDir()) . '/plugins/' . $plugin['dir'] . '/data/names.json';
                if ($plugin['kind'] === 'layer' && is_file($names)) {
                    TempDir::write($plugins . '/' . $plugin['dir'] . '/data/names.json', $id === 'water' ? '{broken' : (string) file_get_contents($names));
                }
            }
            $catalog = (new LayerLoader(Fixtures::generated(), $plugins, $this->config()))->catalog([]);

            self::assertSame('weather', $catalog->get(LayerId::from('weather'), Scope::AT)->layer->value, 'the other layers work');
            try {
                $catalog->get(LayerId::from('water'), Scope::AT);
                self::fail('a broken layer was defined');
            } catch (AssertionFailedError $e) {
                throw $e; // self::fail() above, not the expected error
            } catch (\RuntimeException $e) {
                self::assertStringStartsWith('Layer water could not be created', $e->getMessage());
            }
        } finally {
            TempDir::remove($plugins);
        }
    }

    /**
     * A source in config.php that does not exist is a configuration error, like a misspelled layer.
     *
     * @param array<string, mixed> $settings
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unknownSources')]
    public function testUnknownSourcesInTheConfigurationAreRefused(array $settings, string $message): void
    {
        $dir = sys_get_temp_dir() . '/cs-composition';
        $config = Config::fromArray(['paths' => ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir], ...$settings]);

        $this->expectException(ConfigError::class);
        $this->expectExceptionMessage($message);
        (new FetcherFactory($config, Fixtures::generated(), 'test', microtime(true)))->plans();
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function unknownSources(): iterable
    {
        yield 'source settings' => [['sources' => ['pegelonlin' => ['enabled' => false]]], 'sources: unknown source pegelonlin'];
        yield 'size limit' => [['http' => ['maxBytesBySource' => ['pegelonlin' => 1000]]], 'http.maxBytesBySource: unknown source pegelonlin'];
        yield 'time limit' => [['http' => ['requestTimeoutBySource' => ['pegelonlin' => 5]]], 'http.requestTimeoutBySource: unknown source pegelonlin'];
    }

    /** The check names switched-off sources with their reason. */
    public function testTheCheckNamesSwitchedOffSources(): void
    {
        $dir = sys_get_temp_dir() . '/cs-composition';
        $config = Config::fromArray(['paths' => ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir], 'sources' => ['meteoalarm-ch' => ['enabled' => false, 'reason' => 'format change']]]);
        $results = (new EnvironmentCheck())->run($config, Fixtures::generated(), new FetcherFactory($config, Fixtures::generated(), 'test', microtime(true)));

        self::assertContains([true, 'Source meteoalarm-ch switched off: format change (sources.meteoalarm-ch.enabled)'], $results);
    }

    private function config(): Config
    {
        $dir = sys_get_temp_dir() . '/cs-composition';

        return Config::fromArray(['paths' => ['data' => $dir, 'state' => $dir, 'cache' => $dir, 'locks' => $dir, 'logs' => $dir]]);
    }
}
