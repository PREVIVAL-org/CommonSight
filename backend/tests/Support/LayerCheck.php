<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Domain\Snapshot\ContentHash;
use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Infrastructure\Recording\Cassette;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Sdk\Plugin\SourceDescription;

/**
 * Checks every layer package of a folder the same way, without any entry per layer (concept: layers as plugins, L5):
 * LAYER.md, its name as a text, and in every scope a snapshot assembled from the recordings of its sources that is
 * valid against the contract (including the key figures of the layer) with every text key known. The time of a scope
 * is that of its newest recording, so the check needs no time per layer.
 */
final class LayerCheck
{
    /** Time for scopes without recordings (e.g. a layer without a source in a country). */
    private const WITHOUT_RECORDING = '2026-01-01T00:00:00Z';

    /** @var array<string, string> folder of each source, by id */
    private array $sourceDirs = [];

    /** @return array<string, list<string>> problems per layer id, empty lists for layers without problems */
    public function problems(string $pluginsDir): array
    {
        $layers = [];
        $sources = [];
        $dirs = [];
        foreach ((new PluginDiscovery())->discover($pluginsDir) as $id => $plugin) {
            $description = (new ($plugin['factory'])())->describe();
            if ($description instanceof LayerDescription) {
                $layers[$id] = $description;
            } elseif ($description instanceof SourceDescription) {
                $sources[$id] = $description;
                $dirs[$id] = $pluginsDir . '/' . $plugin['dir'];
            }
        }
        $this->sourceDirs = $dirs;
        $problems = [];
        foreach ($layers as $id => $layer) {
            $problems[$id] = $this->problemsOf($layer, $pluginsDir, $sources);
        }

        return $problems;
    }

    /**
     * @param array<string, SourceDescription> $sources
     * @return list<string>
     */
    private function problemsOf(LayerDescription $layer, string $pluginsDir, array $sources): array
    {
        $dir = $pluginsDir . '/layers/' . $layer->id;
        $problems = is_file($dir . '/LAYER.md') ? [] : ['LAYER.md missing'];
        if (!isset(SnapshotContract::messages()['layer.' . $layer->id . '.name'])) {
            $problems[] = 'text layer.' . $layer->id . '.name missing';
        }
        foreach ($layer->scopes() as $scope) {
            $now = $this->newestRecording($layer->id, $scope, $pluginsDir, $sources);
            array_push($problems, ...array_map(static fn(string $p): string => $scope->value . ': ' . $p, $this->snapshotProblems(LayerId::from($layer->id), $scope, $now)));
        }

        return $problems;
    }

    /** @return list<string> */
    private function snapshotProblems(LayerId $layer, Scope $scope, string $now): array
    {
        $harness = new LayerHarness();
        $assembly = $harness->run($layer, $scope, $now);
        if ($assembly->snapshot === null) {
            return ['no snapshot from the recordings: ' . ($assembly->failure->key ?? '')];
        }
        $snapshot = $assembly->snapshot;
        $problems = [];
        $violations = SnapshotContract::violations((new ContentHash())->json($snapshot), 'snapshot.schema.json');
        if ($violations !== null) {
            $problems[] = 'snapshot violates the contract: ' . $violations;
        }
        foreach (SnapshotContract::missingMessageKeys($snapshot) as $key) {
            $problems[] = 'text missing: ' . $key;
        }
        if ($snapshot->status === FeedStatus::Error) {
            $problems[] = 'status error: ' . json_encode($snapshot->issues, JSON_UNESCAPED_UNICODE);
        }

        return $problems;
    }

    /** @param array<string, SourceDescription> $sources */
    private function newestRecording(string $layer, Scope $scope, string $pluginsDir, array $sources): string
    {
        $newest = null;
        foreach ($sources as $id => $source) {
            if ($source->layer !== $layer || !in_array($scope, $source->scopes, true)) {
                continue;
            }
            $cassette = Cassette::read($this->sourceDirs[$id] . '/tests/responses/' . $scope->value);
            if ($cassette !== null && ($newest === null || $cassette->recordedAt->isAfter($newest))) {
                $newest = $cassette->recordedAt;
            }
        }

        return $newest?->toIso() ?? self::WITHOUT_RECORDING;
    }
}
