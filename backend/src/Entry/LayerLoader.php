<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Domain\Layer\LayerCatalog;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Plugin\FilePluginData;
use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerEnvironment;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerPluginFactory;
use CommonSight\Sdk\Layer\LayerSetting;
use CommonSight\Sdk\Layer\LayerSettings;
use CommonSight\Sdk\Layer\LayerSources;
use CommonSight\Sdk\Plugin\SourceDescription;

/**
 * Creates the layer plugins found at build time (generated/plugins.php) and composes the layer catalog from them
 * (concept: layers as plugins): each layer gets its data folder, its validated settings and the region mechanics of
 * the core; the sources of a layer in a scope are the ones that name it. In a country scope the core adds the nearby
 * countries and regions to the steps of the layer (ADR 0038).
 */
final class LayerLoader
{
    /** @var array<string, array{LayerPluginFactory, LayerDescription, string}>|null */
    private ?array $layers = null;

    public function __construct(
        private readonly GeneratedData $data,
        private readonly string $pluginsDir,
        private readonly Config $config,
    ) {}

    /** @return list<LayerDescription> by their order */
    public function descriptions(): array
    {
        return array_values(array_map(static fn(array $layer): LayerDescription => $layer[1], $this->layers()));
    }

    /**
     * Creates every layer at once, so that an invalid setting is a configuration error at the start, not in a run.
     *
     * @param list<SourceDescription> $sources all sources, for the names and links composed from them
     * @throws ConfigError for an invalid layer setting
     */
    public function catalog(array $sources): LayerCatalog
    {
        $this->refuseUnknownLayers();
        $mechanics = new CoreLayerMechanics($this->data, Vicinity::km($this->config, $this->data));
        $factories = [];
        foreach ($this->layers() as [$factory, $description, $dir]) {
            $plugin = $this->createIsolated($factory, $description, $dir, $mechanics);
            foreach ($description->scopes() as $scope) {
                $factories[LayerCatalog::key(LayerId::from($description->id), $scope)] = $plugin instanceof LayerPlugin
                    ? fn(): LayerDefinition => $this->definition($plugin, $description, $scope, $sources, $mechanics)
                    : static fn(): LayerDefinition => throw new \RuntimeException('Layer ' . $description->id . ' could not be created: ' . $plugin->getMessage(), 0, $plugin);
            }
        }

        return LayerCatalog::lazy($factories);
    }

    /**
     * A layer package that cannot be created (e.g. a broken data file) fails only its own layer, when it is assembled;
     * an invalid setting stays a configuration error that stops the start (L-D5). A layer reports an invalid
     * combination of its settings by an InvalidArgumentException from create() (e.g. radiation: high below warning).
     *
     * @throws ConfigError
     */
    private function createIsolated(LayerPluginFactory $factory, LayerDescription $description, string $dir, CoreLayerMechanics $mechanics): LayerPlugin|\Throwable
    {
        try {
            return $this->create($factory, $description, $dir, $mechanics);
        } catch (ConfigError $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    private function create(LayerPluginFactory $factory, LayerDescription $description, string $dir, CoreLayerMechanics $mechanics): LayerPlugin
    {
        $environment = new LayerEnvironment(new FilePluginData($this->pluginsDir . '/' . $dir . '/data', $this->data), $this->settings($description), $mechanics);
        try {
            return $factory->create($environment);
        } catch (\InvalidArgumentException $e) {
            throw new ConfigError('layers.' . $description->id . '.settings: ' . $e->getMessage(), 0, $e);
        }
    }

    /** @param list<SourceDescription> $sources */
    private function definition(LayerPlugin $plugin, LayerDescription $description, Scope $scope, array $sources, CoreLayerMechanics $mechanics): LayerDefinition
    {
        $own = array_values(array_filter($sources, fn(SourceDescription $s): bool => $s->layer === $description->id && in_array($scope, $s->scopes, true) && $this->runs($s)));
        // By rank like the plans and the deduplication: the rank of the configuration (sources.<id>.order) first.
        $rank = fn(SourceDescription $s): int => $this->config->sources[$s->id]->order ?? $s->order;
        usort($own, static fn(SourceDescription $a, SourceDescription $b): int => [$rank($a), $a->id] <=> [$rank($b), $b->id]);
        $definition = $plugin->definition($scope, new LayerSources($own));
        if (!$scope->isCountry() || $definition->steps === []) {
            return $definition;
        }

        return new LayerDefinition($definition->layer, $definition->scope, $definition->sourceName, $definition->sourceUrl, $definition->note, [...$definition->steps, $mechanics->nearby()], $definition->stats);
    }

    /**
     * Whether the source runs, like the schedule decides it: not switched off and with all its secrets. Only these name
     * the layer (its name and link come from the sources that run).
     */
    private function runs(SourceDescription $source): bool
    {
        $settings = $this->config->sources[$source->id] ?? null;
        $missing = array_filter($source->secrets, static fn(string $name): bool => ($settings->secrets[$name] ?? '') === '');

        return ($settings->enabled ?? true) && $missing === [];
    }

    /**
     * Settings for a layer that does not exist (a typo, or a package that is not installed) would silently be ignored.
     *
     * @throws ConfigError
     */
    private function refuseUnknownLayers(): void
    {
        $known = array_keys($this->layers());
        $unknown = array_diff(array_keys($this->config->layerSettings), $known);
        if ($unknown !== []) {
            throw new ConfigError(sprintf('layers: unknown layer %s (known: %s)', implode(', ', $unknown), implode(', ', $known)));
        }
    }

    /** @throws ConfigError for an undeclared or invalid setting */
    private function settings(LayerDescription $description): LayerSettings
    {
        $configured = $this->config->layerSettings[$description->id] ?? [];
        $declared = array_map(static fn(LayerSetting $s): string => $s->name, $description->settings);
        $unknown = array_diff(array_keys($configured), $declared);
        if ($unknown !== []) {
            throw new ConfigError(sprintf('layers.%s.settings: unknown %s (known: %s)', $description->id, implode(', ', $unknown), implode(', ', $declared) ?: 'none'));
        }
        $values = [];
        foreach ($description->settings as $setting) {
            try {
                $values[$setting->name] = array_key_exists($setting->name, $configured) ? $setting->accept($configured[$setting->name]) : $setting->default;
            } catch (\InvalidArgumentException $e) {
                throw new ConfigError('layers.' . $description->id . '.settings.' . $e->getMessage(), 0, $e);
            }
        }

        return new LayerSettings($values);
    }

    /** @return array<string, array{LayerPluginFactory, LayerDescription, string}> by id, in their order */
    private function layers(): array
    {
        if ($this->layers === null) {
            $this->layers = [];
            foreach ($this->data->plugins() as $id => $plugin) {
                if ($plugin['kind'] !== PluginDiscovery::LAYER) {
                    continue;
                }
                $factory = new ($plugin['factory'])();
                if (!$factory instanceof LayerPluginFactory) {
                    throw new \RuntimeException('Layer ' . $id . ': not a ' . LayerPluginFactory::class . ' (rebuild with bin/build-contract.php)');
                }
                $this->layers[$id] = [$factory, $factory->describe(), $plugin['dir']];
            }
            uasort($this->layers, static fn(array $a, array $b): int => [$a[1]->order, $a[1]->id] <=> [$b[1]->order, $b[1]->id]);
        }

        return $this->layers;
    }
}
