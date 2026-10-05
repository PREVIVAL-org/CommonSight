<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Infrastructure\Http\SourceHttpClient;
use CommonSight\Infrastructure\Plugin\FilePluginData;
use CommonSight\Infrastructure\Plugin\FilePluginStore;
use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Infrastructure\Recording\Cassette;
use CommonSight\Infrastructure\Recording\ReplayHttpClient;
use CommonSight\Infrastructure\Storage\AtomicFile;
use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\Secrets;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceRun;

/**
 * Checks every plugin of a folder the same way, without any entry per plugin (concept: sources as plugins, 7): valid
 * description, PROFILE.md, a recording per scope, a successful run on the recording, every item valid against the
 * schema, every text key known (core texts and the plugin's own).
 */
final class PluginCheck
{
    /** @return array<string, list<string>> problems per plugin id, empty lists for plugins without problems */
    public function problems(string $pluginsDir): array
    {
        $problems = [];
        foreach ((new PluginDiscovery(array_map(static fn($m): string => $m->id->value, Fixtures::generated()->layerMetas())))->discover($pluginsDir) as $id => $plugin) {
            if ($plugin['kind'] !== PluginDiscovery::SOURCE) {
                continue; // layer packages have their own check (concept: layers as plugins, L5)
            }
            $factory = new ($plugin['factory'])();
            if (!$factory instanceof SourcePluginFactory) {
                $problems[$id] = ['not a plugin factory'];
                continue;
            }
            $problems[$id] = $this->problemsOf($factory, $pluginsDir . '/' . $plugin['dir']);
        }

        return $problems;
    }

    /** @return list<string> */
    private function problemsOf(SourcePluginFactory $factory, string $dir): array
    {
        $description = $factory->describe();
        $problems = is_file($dir . '/PROFILE.md') ? [] : ['PROFILE.md missing'];
        foreach ($description->scopes as $scope) {
            $cassette = Cassette::read($dir . '/tests/responses/' . $scope->value);
            if ($cassette === null) {
                $problems[] = $scope->value . ': no recording in tests/responses/' . $scope->value . '/';
                continue;
            }
            array_push($problems, ...array_map(static fn(string $p): string => $scope->value . ': ' . $p, $this->runProblems($factory, $description, $dir, $scope, $cassette)));
        }

        return $problems;
    }

    /** @return list<string> */
    private function runProblems(SourcePluginFactory $factory, SourceDescription $description, string $dir, Scope $scope, Cassette $cassette): array
    {
        $store = TempDir::create('plugin-check');
        try {
            $outcome = $factory->create($this->environment($description, $dir, $cassette, $store))->fetch(new SourceRun($scope, $cassette->recordedAt));
        } catch (\Throwable $e) {
            return ['the run threw ' . $e::class . ': ' . $e->getMessage()];
        } finally {
            TempDir::remove($store);
        }
        if (!$outcome->succeeded()) {
            return ['the run on the recording failed: ' . $outcome->failure];
        }
        if ($outcome->items === [] && $description->expectations->emptyIsFailure) {
            return ['the recording yields no items, but the source expects some'];
        }

        return $this->itemProblems($outcome, $this->knownTexts($dir));
    }

    /**
     * @param array<string, true> $known
     * @return list<string>
     */
    private function itemProblems(SourceOutcome $outcome, array $known): array
    {
        $problems = [];
        foreach ($outcome->items as $item) {
            $json = json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $violations = SnapshotContract::violations($json, 'item.schema.json');
            if ($violations !== null) {
                $problems[] = 'item ' . $item->common()->id . ' violates the schema: ' . $violations;
            }
            preg_match_all('/"key":"([^"]+)"/', $json, $matches);
            foreach ($matches[1] as $key) {
                if (!isset($known[$key])) {
                    $problems[] = 'item ' . $item->common()->id . ': text key without text: ' . $key;
                }
            }
        }

        return array_values(array_unique($problems));
    }

    private function environment(SourceDescription $description, string $dir, Cassette $cassette, string $store): PluginEnvironment
    {
        return new PluginEnvironment(
            new SourceHttpClient(new ReplayHttpClient([$cassette]), $description->id),
            new FilePluginStore($store, new AtomicFile(), new FakeClock($cassette->recordedAt)),
            new Secrets(array_fill_keys($description->secrets, 'recorded')),
            new SafeXmlReader(),
            new FilePluginData($dir . '/data', Fixtures::generated()),
        );
    }

    /** @return array<string, true> keys of the core texts, of the joined plugin texts and of the plugin's own file */
    private function knownTexts(string $dir): array
    {
        $own = is_file($dir . '/messages/de.json') ? (array) json_decode((string) file_get_contents($dir . '/messages/de.json'), true) : [];

        return array_fill_keys(array_map('strval', array_keys([...SnapshotContract::messages(), ...$own])), true);
    }
}
