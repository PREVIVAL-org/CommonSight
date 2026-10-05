<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/** Concept "sources as plugins", D1: plugins are found and checked at build time, never by a scan at runtime. */
final class PluginDiscoveryTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = TempDir::create('plugin-discovery');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->dir);
    }

    public function testFindsValidPluginsByTheirManifest(): void
    {
        $this->plugin('providers/quakes', $this->factory('quakes', 'nature'));
        TempDir::write($this->dir . '/notes/README.md', 'a folder without manifest is not a plugin');

        $plugins = (new PluginDiscovery(array_map(static fn($m): string => $m->id->value, Fixtures::generated()->layerMetas())))->discover($this->dir);

        self::assertSame(['quakes'], array_keys($plugins));
        self::assertSame('providers/quakes', $plugins['quakes']['dir'], 'group and folder');
        self::assertStringEndsWith('QuakesFactory', $plugins['quakes']['factory']);
    }

    public function testReportsEveryInvalidPluginAtOnce(): void
    {
        $this->plugin('providers/mismatch', $this->factory('other-id', 'nature'));
        $this->plugin('providers/nolayer', $this->factory('nolayer', 'volcanoes'));
        $this->plugin('providers/nofactory', 'return new \stdClass();');
        $this->plugin('providers/anonymous', 'return new class () implements \CommonSight\Sdk\Plugin\SourcePluginFactory {
            public function describe(): \CommonSight\Sdk\Plugin\SourceDescription { throw new \LogicException(); }
            public function create(\CommonSight\Sdk\Plugin\PluginEnvironment $e): \CommonSight\Sdk\Plugin\SourcePlugin { throw new \LogicException(); }
        };');

        try {
            (new PluginDiscovery(array_map(static fn($m): string => $m->id->value, Fixtures::generated()->layerMetas())))->discover($this->dir);
            self::fail('invalid plugins accepted');
        } catch (AssertionFailedError $e) {
            throw $e; // self::fail() above, not the expected error
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('providers/anonymous: the factory must be a named class', $e->getMessage());
            self::assertStringContainsString('providers/mismatch: source id "other-id" differs from the folder name', $e->getMessage());
            self::assertStringContainsString('providers/nofactory: plugin.php must return a', $e->getMessage());
            self::assertStringContainsString('providers/nolayer: unknown layer: volcanoes', $e->getMessage());
        }
    }

    /** L-D1: layer and source packages in their groups; the layers of the folder are the ones sources may name. */
    public function testLayerPackagesNameTheLayersOfTheSources(): void
    {
        $this->plugin('layers/water', 'return new \\CommonSight\\Plugin\\WaterLayer\\WaterLayerFactory();');
        TempDir::write($this->dir . '/layers/water/frontend/map.ts', '');
        $this->plugin('providers/gauges', $this->factory('gauges', 'water'));

        $plugins = (new PluginDiscovery())->discover($this->dir);

        self::assertSame(['gauges' => 'source', 'water' => 'layer'], array_map(static fn(array $p): string => $p['kind'], $plugins));
    }

    /** Frontend code in a source package would be ignored: it belongs to the layer. */
    public function testASourcePackageHasNoFrontendPart(): void
    {
        $this->plugin('layers/water', 'return new \\CommonSight\\Plugin\\WaterLayer\\WaterLayerFactory();');
        TempDir::write($this->dir . '/layers/water/frontend/map.ts', '');
        $this->plugin('providers/gauges', $this->factory('gauges', 'water'));
        TempDir::write($this->dir . '/providers/gauges/frontend/map.ts', '');

        $this->expectExceptionMessage('providers/gauges: a source package has no frontend part');
        (new PluginDiscovery())->discover($this->dir);
    }

    /** A source for a scope its layer does not have would run, but its outcome would belong to no layer. */
    public function testASourceNeedsTheScopesOfItsLayer(): void
    {
        $this->plugin('layers/warnings', 'return new \\CommonSight\\Plugin\\WarningsLayer\\WarningsLayerFactory();');
        TempDir::write($this->dir . '/layers/warnings/frontend/map.ts', '');
        $this->plugin('providers/beyond', $this->factory('beyond', 'warnings', 'Scope::AT, Scope::Border'));

        $this->expectExceptionMessage('providers/beyond: the layer warnings has no scope border');
        (new PluginDiscovery())->discover($this->dir);
    }

    public function testALayerPackageNeedsItsFrontendPart(): void
    {
        $this->plugin('layers/nature', 'return new \\CommonSight\\Plugin\\NatureLayer\\NatureLayerFactory();');
        $this->expectExceptionMessage('layers/nature: a layer package needs its frontend part');
        (new PluginDiscovery())->discover($this->dir);
    }

    /** Layers lie in layers/, sources in news/ or providers/; nothing directly in plugins/, no id twice. */
    public function testEveryPackageLiesInTheGroupOfItsKind(): void
    {
        $this->plugin('layers/quakes', $this->factory('quakes', 'nature'));
        $this->plugin('tools/radar', $this->factory('radar', 'nature'));
        $this->plugin('loose', $this->factory('loose', 'nature'));
        $this->plugin('providers/twice', $this->factory('twice', 'nature'));
        $this->plugin('news/twice', $this->factory('twice', 'nature'));

        try {
            (new PluginDiscovery(array_map(static fn($m): string => $m->id->value, Fixtures::generated()->layerMetas())))->discover($this->dir);
            self::fail('misplaced plugins accepted');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('layers/quakes: a source belongs in news/ or providers/', $e->getMessage());
            self::assertStringContainsString('tools/radar: unknown group tools/', $e->getMessage());
            self::assertStringContainsString('loose: a plugin lies in one of the groups layers/, news/, providers/, auth/', $e->getMessage());
            self::assertStringContainsString('the id is already used by', $e->getMessage());
        }
    }

    /** ACCESS-AND-BRANDING A-D4: authentication providers in auth/, nowhere else. */
    public function testAuthProvidersLieInTheirGroup(): void
    {
        $this->plugin('auth/woltlab', 'return new \\CommonSight\\Plugin\\WoltlabAuth\\WoltlabAuthFactory();');
        self::assertSame(['woltlab' => ['kind' => 'auth', 'factory' => 'CommonSight\\Plugin\\WoltlabAuth\\WoltlabAuthFactory', 'dir' => 'auth/woltlab']], (new PluginDiscovery())->discover($this->dir));

        TempDir::remove($this->dir . '/auth');
        $this->plugin('providers/woltlab', 'return new \\CommonSight\\Plugin\\WoltlabAuth\\WoltlabAuthFactory();');
        $this->expectExceptionMessage('providers/woltlab: an auth provider belongs in auth/');
        (new PluginDiscovery())->discover($this->dir);
    }

    public function testAnEmptyFolderHasNoPlugins(): void
    {
        self::assertSame([], (new PluginDiscovery(array_map(static fn($m): string => $m->id->value, Fixtures::generated()->layerMetas())))->discover($this->dir));
    }

    private function plugin(string $dir, string $manifestBody): void
    {
        TempDir::write($this->dir . '/' . $dir . '/plugin.php', "<?php\n\ndeclare(strict_types=1);\n\n" . $manifestBody . "\n");
    }

    /** Manifest code that defines a factory class in its own namespace and returns it. */
    private function factory(string $id, string $layer, string $scopes = 'Scope::AT'): string
    {
        $namespace = 'CommonSight\\Tests\\Discovery\\P' . bin2hex(random_bytes(4));
        $class = ucfirst(str_replace('-', '', $id)) . 'Factory';

        return <<<PHP
            namespace {$namespace};

            use CommonSight\\Model\\Value\\Scope;
            use CommonSight\\Sdk\\Plugin\\{Attribution, PluginEnvironment, SourceDescription, SourcePlugin, SourcePluginFactory, SourceSchedule};
            use CommonSight\\Sdk\\Source\\SourceExpectations;

            final class {$class} implements SourcePluginFactory
            {
                public function describe(): SourceDescription
                {
                    return new SourceDescription('{$id}', 'Test', new Attribution('Test', 'https://example.org/'), '{$layer}', [{$scopes}], new SourceSchedule(60), SourceExpectations::events());
                }

                public function create(PluginEnvironment \$environment): SourcePlugin
                {
                    throw new \\LogicException('not needed');
                }
            }

            return new {$class}();
            PHP;
    }
}
