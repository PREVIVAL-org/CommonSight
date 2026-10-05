<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Plugin;

use CommonSight\Sdk\Auth\AuthProviderFactory;
use CommonSight\Sdk\Layer\LayerPluginFactory;
use CommonSight\Sdk\Plugin\SourcePluginFactory;

/**
 * Finds the plugin packages of a folder by their manifest <group>/<id>/plugin.php and checks them at build time (concept
 * D1, L-D1): the manifest returns a factory of a source or of a layer with a constructor without parameters, the
 * description is valid, its id is the folder name; layers lie in the group layers/, sources in news/ (news feeds) or
 * providers/ (data providers), authentication providers in auth/ (ACCESS-AND-BRANDING A-D4); a layer package has a frontend part, a source package none; the layer of a source is
 * one of the layer packages and has every scope of the source. Reports all problems at once.
 */
final class PluginDiscovery
{
    public const SOURCE = 'source';
    public const LAYER = 'layer';
    public const AUTH = 'auth';
    /** The groups of the plugins folder and the kind of package each holds. */
    public const GROUPS = ['layers' => self::LAYER, 'news' => self::SOURCE, 'providers' => self::SOURCE, 'auth' => self::AUTH];

    /** @param list<string> $knownLayers layers that exist besides the layer packages of the folder (e.g. for test folders) */
    public function __construct(private readonly array $knownLayers = []) {}

    /**
     * @return array<string, array{kind: string, factory: class-string<SourcePluginFactory|LayerPluginFactory|AuthProviderFactory>, dir: string}> by id; dir is <group>/<id>
     * @throws \RuntimeException listing every invalid plugin
     */
    public function discover(string $pluginsDir): array
    {
        [$factories, $problems] = $this->factories($pluginsDir);
        $layerScopes = $this->layerScopes($factories);
        $layers = [...$this->knownLayers, ...array_keys($layerScopes)];
        $plugins = [];
        foreach ($factories as $dir => $factory) {
            $kind = match (true) {
                $factory instanceof LayerPluginFactory => self::LAYER,
                $factory instanceof AuthProviderFactory => self::AUTH,
                default => self::SOURCE,
            };
            $problem = $this->groupProblem($dir, $kind)
                ?? (isset($plugins[basename($dir)]) ? 'the id is already used by ' . $plugins[basename($dir)]['dir'] : null)
                ?? match ($kind) {
                    self::SOURCE => $factory instanceof SourcePluginFactory ? $this->sourceProblem($factory, $pluginsDir . '/' . $dir, $layers, $layerScopes) : null,
                    self::LAYER => $this->layerProblem($pluginsDir . '/' . $dir),
                    default => is_dir($pluginsDir . '/' . $dir . '/frontend') ? 'an auth provider has no frontend part' : null,
                };
            if ($problem !== null) {
                $problems[] = $dir . ': ' . $problem;
                continue;
            }
            $plugins[basename($dir)] = ['kind' => $kind, 'factory' => $factory::class, 'dir' => $dir];
        }
        if ($problems !== []) {
            sort($problems);
            throw new \RuntimeException("Invalid plugins:\n  " . implode("\n  ", $problems));
        }
        ksort($plugins);

        return $plugins;
    }

    /**
     * The factories of the manifests, by <group>/<id>, and the problems of the manifests that cannot be loaded or lie
     * outside a group.
     *
     * @return array{array<string, SourcePluginFactory|LayerPluginFactory|AuthProviderFactory>, list<string>}
     */
    private function factories(string $pluginsDir): array
    {
        $factories = [];
        $problems = [];
        foreach (glob($pluginsDir . '/*/*/plugin.php') ?: [] as $manifest) {
            $dir = basename(dirname($manifest, 2)) . '/' . basename(dirname($manifest));
            try {
                $factories[$dir] = $this->factory($manifest, $dir);
            } catch (\Throwable $e) {
                $problems[] = $dir . ': ' . $e->getMessage();
            }
        }
        foreach (glob($pluginsDir . '/*/plugin.php') ?: [] as $manifest) {
            $problems[] = basename(dirname($manifest)) . ': a plugin lies in one of the groups ' . implode('/, ', array_keys(self::GROUPS)) . '/';
        }

        return [$factories, $problems];
    }

    /**
     * @param array<string, SourcePluginFactory|LayerPluginFactory|AuthProviderFactory> $factories
     * @return array<string, list<string>> the scopes of each layer package, by layer id
     */
    private function layerScopes(array $factories): array
    {
        $scopes = [];
        foreach ($factories as $factory) {
            if ($factory instanceof LayerPluginFactory) {
                $description = $factory->describe();
                $scopes[$description->id] = array_map(static fn($scope): string => $scope->value, $description->scopes());
            }
        }

        return $scopes;
    }

    private function factory(string $manifest, string $dir): SourcePluginFactory|LayerPluginFactory|AuthProviderFactory
    {
        $factory = (static fn(): mixed => require $manifest)();
        if (!$factory instanceof SourcePluginFactory && !$factory instanceof LayerPluginFactory && !$factory instanceof AuthProviderFactory) {
            throw new \RuntimeException('plugin.php must return a ' . SourcePluginFactory::class . ', a ' . LayerPluginFactory::class . ' or a ' . AuthProviderFactory::class);
        }
        $class = new \ReflectionClass($factory);
        if ($class->isAnonymous()) {
            throw new \RuntimeException('the factory must be a named class, the core creates it by its name');
        }
        $constructor = $class->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new \RuntimeException('the factory needs a constructor without parameters');
        }
        $id = $factory->describe()->id;
        if ($id !== basename($dir)) {
            throw new \RuntimeException(sprintf('%s id "%s" differs from the folder name', match (true) {
                $factory instanceof LayerPluginFactory => 'layer',
                $factory instanceof AuthProviderFactory => 'auth provider',
                default => 'source',
            }, $id));
        }

        return $factory;
    }

    /**
     * @param list<string> $layers
     * @param array<string, list<string>> $layerScopes the scopes of the layer packages of the folder
     */
    private function sourceProblem(SourcePluginFactory $factory, string $dir, array $layers, array $layerScopes): ?string
    {
        if (is_dir($dir . '/frontend')) {
            return 'a source package has no frontend part; the frontend of its data belongs to its layer';
        }
        $description = $factory->describe();
        if (!in_array($description->layer, $layers, true)) {
            return 'unknown layer: ' . $description->layer;
        }
        // A source for a scope its layer does not have would run, but its outcome would belong to no layer.
        $foreign = array_diff(array_map(static fn($scope): string => $scope->value, $description->scopes), $layerScopes[$description->layer] ?? []);

        return isset($layerScopes[$description->layer]) && $foreign !== [] ? sprintf('the layer %s has no scope %s', $description->layer, implode(', ', $foreign)) : null;
    }

    private function groupProblem(string $dir, string $kind): ?string
    {
        $group = dirname($dir);
        if (!isset(self::GROUPS[$group])) {
            return 'unknown group ' . $group . '/ (known: ' . implode('/, ', array_keys(self::GROUPS)) . '/)';
        }

        $name = [self::SOURCE => 'a source', self::LAYER => 'a layer', self::AUTH => 'an auth provider'][$kind] ?? $kind;

        return self::GROUPS[$group] === $kind ? null : sprintf('%s belongs in %s', $name, implode('/ or ', array_keys(array_filter(self::GROUPS, static fn(string $k): bool => $k === $kind))) . '/');
    }

    private function layerProblem(string $dir): ?string
    {
        return is_dir($dir . '/frontend') ? null : 'a layer package needs its frontend part (frontend/)';
    }
}
