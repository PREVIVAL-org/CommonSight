<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Domain\Layer\LayerCatalog;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Model\Value\LayerId;
use CommonSight\Port\SourceHealthStore;
use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Layer\LayerSetting;

/**
 * Checks the fetcher's environment: PHP version, extensions, directories, generated master data, the sources with
 * their lanes, whether the typical run times of a lane fit its budget, and the settings of the layers (Architecture 4.12; concept: sources as
 * plugins, 6.1, D8). Lanes and intervals themselves are checked when the plans are made (configuration errors).
 */
final class EnvironmentCheck
{
    private const REQUIRED = ['curl', 'json', 'mbstring', 'xmlreader', 'dom', 'zlib'];
    /** brotli: smaller snapshots; pdo_mysql: the WoltLab auth provider */
    private const OPTIONAL = ['brotli', 'pdo_mysql'];
    /** A lane whose sources typically need more than this share of its budget gets a warning (D8). */
    private const BUDGET_WARNING_SHARE = 0.7;

    /** @return list<array{bool, string}> result per check: passed, description */
    public function run(Config $config, GeneratedData $data, FetcherFactory $factory): array
    {
        $plans = $factory->plans();
        $health = $factory->healthStore();
        $results = [[version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP ' . PHP_VERSION . ' (at least 8.3)']];
        foreach (self::REQUIRED as $extension) {
            $results[] = [extension_loaded($extension), 'Extension ' . $extension];
        }
        foreach (self::OPTIONAL as $extension) {
            $results[] = [true, sprintf('Extension %s (optional): %s', $extension, extension_loaded($extension) ? 'present' : 'missing')];
        }
        foreach ((array) $config->paths as $name => $path) {
            $results[] = [$this->writableDirectory($path), sprintf('Directory %s writable: %s', $name, $path)];
        }
        $results[] = $this->generated($data);
        $sources = array_unique(array_map(static fn(SourcePlan $p): string => $p->id(), $plans->all()));
        $results[] = [$sources !== [], sprintf('Source plugins: %d', count($sources))];
        array_push($results, ...$this->missingSecrets($plans));
        array_push($results, ...$this->switchedOff($config));
        $results[] = $this->access($config, $data);
        foreach ($config->lanes->names() as $lane) {
            $results[] = $this->lane($lane, $config->lanes->budget($lane), $plans->inLane($lane), $health);
        }
        array_push($results, ...$this->layerSettings($config, $factory));

        return $results;
    }

    /**
     * A source without its secrets does not run and shows "not set up"; a hint, not an error (e.g. no API key yet).
     *
     * @return list<array{bool, string}>
     */
    private function missingSecrets(SourcePlans $plans): array
    {
        $hints = [];
        foreach ($plans->all() as $plan) {
            if ($plan->source->missingSecrets !== []) {
                $hints[$plan->id()] = [true, sprintf('Source %s not set up: secret %s missing (sources.%s.secrets in config.php)', $plan->id(), implode(', ', $plan->source->missingSecrets), $plan->id())];
            }
        }

        return array_values($hints);
    }

    /**
     * Sources the operator switched off, with the reason given in config.php; a hint, not an error.
     *
     * @return list<array{bool, string}>
     */
    private function switchedOff(Config $config): array
    {
        $hints = [];
        foreach ($config->sources as $id => $source) {
            if (!$source->enabled) {
                $hints[] = [true, sprintf('Source %s switched off%s (sources.%s.enabled)', $id, $source->reason === null ? '' : ': ' . $source->reason, $id)];
            }
        }

        return $hints;
    }

    private function writableDirectory(string $path): bool
    {
        return (is_dir($path) || @mkdir($path, 0755, true)) && is_writable($path);
    }

    /** @return array{bool, string} */
    private function generated(GeneratedData $data): array
    {
        try {
            return [$data->layerMetas() !== [], 'Generated master data readable'];
        } catch (\RuntimeException $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * A warning only: the lane still runs, the most overdue sources first, and the rest follows in the next run.
     *
     * @param list<SourcePlan> $plans
     * @return array{bool, string}
     */
    private function lane(string $lane, int $budgetSec, array $plans, SourceHealthStore $health): array
    {
        $typicalMs = 0;
        foreach ($plans as $plan) {
            $typicalMs += $health->read($plan->id(), $plan->scope)->typicalDurationMs ?? 0;
        }
        $sources = count(array_unique(array_map(static fn(SourcePlan $p): string => $p->id(), $plans)));
        $text = sprintf('Lane %s: %d sources in %d scopes, typically %.0f s of %d s budget', $lane, $sources, count($plans), $typicalMs / 1000, $budgetSec);
        if ($typicalMs > $budgetSec * 1000 * self::BUDGET_WARNING_SHARE) {
            $text .= sprintf(' (warning: more than %d %%; move sources to another lane or raise the budget)', self::BUDGET_WARNING_SHARE * 100);
        }

        return [true, $text];
    }

    /**
     * The settings of the layers with their values and defaults (L-D5); an invalid setting fails the check, because the
     * fetcher refuses to start with it.
     *
     * @return list<array{bool, string}>
     */
    private function layerSettings(Config $config, FetcherFactory $factory): array
    {
        $layers = $factory->layers();
        try {
            $catalog = $layers->catalog($factory->sourceDescriptions());
        } catch (ConfigError $e) {
            return [[false, $e->getMessage()]];
        }
        $results = $this->definitionProblems($catalog, $layers->descriptions());
        foreach ($layers->descriptions() as $layer) {
            $own = $config->layerSettings[$layer->id] ?? [];
            $values = array_map(static fn(LayerSetting $s): string => self::setting($s, $own[$s->name] ?? null), $layer->settings);
            if ($values !== []) {
                $results[] = [true, sprintf('Layer %s: %s (layers.%s.settings)', $layer->id, implode(', ', $values), $layer->id)];
            }
        }

        return $results;
    }

    /** "highUSvH 2.0 (default 1.0)", or "highUSvH 1.0 (default)" when the configuration leaves the default. */
    private static function setting(LayerSetting $setting, mixed $configured): string
    {
        $value = is_int($configured) || is_float($configured) ? (float) $configured : $setting->default;
        if ($value === $setting->default) {
            return sprintf('%s %s (default)', $setting->name, self::number($value));
        }

        return sprintf('%s %s (default %s)', $setting->name, self::number($value), self::number($setting->default));
    }

    /** Numbers as written in config.php: 1.0 stays "1.0", 0.25 stays "0.25". */
    private static function number(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return str_contains($text, '.') ? $text : $text . '.0';
    }

    /**
     * Every layer in every scope can be defined (data of its package, names per scope); otherwise it would fail at each
     * assembly.
     *
     * @param list<LayerDescription> $descriptions
     * @return list<array{bool, string}>
     */
    private function definitionProblems(LayerCatalog $catalog, array $descriptions): array
    {
        $problems = [];
        foreach ($descriptions as $layer) {
            foreach ($layer->scopes() as $scope) {
                try {
                    $catalog->get(LayerId::from($layer->id), $scope);
                } catch (\Throwable $e) {
                    $problems[] = [false, sprintf('Layer %s in %s: %s', $layer->id, $scope->value, $e->getMessage())];
                }
            }
        }

        return $problems;
    }

    /**
     * Who sees the start page (ACCESS-AND-BRANDING A-D3): everyone, or the members of the provider's community.
     *
     * @return array{bool, string}
     */
    private function access(Config $config, GeneratedData $data): array
    {
        if ($config->auth === null) {
            return [true, 'Start page: open to everyone (no auth provider)'];
        }
        try {
            (new AuthLoader($data))->provider($config->auth);
        } catch (ConfigError $e) {
            return [false, 'Start page: ' . $e->getMessage()];
        }

        return [true, sprintf('Start page: members only, provider %s (auth.provider)', $config->auth->provider)];
    }
}
