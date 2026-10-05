<?php

declare(strict_types=1);

namespace CommonSight\Tests\Architecture;

use CommonSight\Tests\Support\PluginIsolation;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/** V13: plugins do not use each other; each has its own namespace (concept: sources as plugins, 7). */
final class PluginIsolationTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = TempDir::create('plugin-isolation');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->dir);
    }

    public function testThePluginsOfTheRepositoryAreIsolated(): void
    {
        self::assertSame([], (new PluginIsolation())->violations(dirname(__DIR__, 3) . '/plugins'));
    }

    public function testFindsReferencesToOtherPlugins(): void
    {
        $this->plugin('alpha', 'Alpha', 'AlphaParser', "use CommonSight\\Plugin\\Beta\\BetaParser;\n");
        $this->plugin('beta', 'Beta', 'BetaParser');

        self::assertSame(
            ['alpha: AlphaParser.php refers to the plugin CommonSight\\Plugin\\Beta'],
            (new PluginIsolation())->violations($this->dir),
        );
    }

    public function testFindsSharedAndMixedNamespaces(): void
    {
        $this->plugin('alpha', 'Alpha', 'AlphaParser');
        $this->plugin('copy', 'Alpha', 'CopyParser');
        $this->plugin('mixed', 'Mixed', 'MixedParser');
        $this->plugin('mixed', 'Other', 'OtherParser');

        self::assertSame([
            'copy: namespace CommonSight\\Plugin\\Alpha is already used by alpha',
            'mixed: all classes must be in one namespace CommonSight\\Plugin\\<Name>, found: Mixed, Other',
        ], (new PluginIsolation())->violations($this->dir));
    }

    private function plugin(string $dir, string $namespace, string $class, string $uses = ''): void
    {
        TempDir::write("{$this->dir}/providers/{$dir}/backend/{$class}.php", "<?php\n\nnamespace CommonSight\\Plugin\\{$namespace};\n\n{$uses}\nfinal class {$class} {}\n");
    }
}
