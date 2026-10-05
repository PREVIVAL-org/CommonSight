<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Application\DueSources;
use CommonSight\Application\FallbackTrigger;
use CommonSight\Application\HealthRecorder;
use CommonSight\Application\LaneRunner;
use CommonSight\Application\LayerComposer;
use CommonSight\Application\LayerPublisher;
use CommonSight\Application\LayerSchedule;
use CommonSight\Application\OutcomeArchive;
use CommonSight\Application\PluginRunner;
use CommonSight\Application\ResultRecorder;
use CommonSight\Application\SourceBatch;
use CommonSight\Application\SourceRefresh;
use CommonSight\Config\Config;
use CommonSight\Domain\Schedule\BackoffPolicy;
use CommonSight\Domain\Schedule\DueSourcePolicy;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Domain\Snapshot\Assembly;
use CommonSight\Domain\Snapshot\ContentHash;
use CommonSight\Domain\Snapshot\IssueCollector;
use CommonSight\Domain\Snapshot\SnapshotAssembler;
use CommonSight\Domain\Source\DriftPolicy;
use CommonSight\Domain\Source\SourceSelection;
use CommonSight\Entry\LayerLoader;
use CommonSight\Entry\PluginLoader;
use CommonSight\Entry\SourcePlanner;
use CommonSight\Infrastructure\Clock\SystemClock;
use CommonSight\Infrastructure\Http\SourceHttpClient;
use CommonSight\Infrastructure\Recording\Cassette;
use CommonSight\Infrastructure\Recording\ReplayHttpClient;
use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\HttpClient;
use CommonSight\Port\Logger;
use CommonSight\Port\PluginStore;
use CommonSight\Sdk\Plugin\Secrets;
use CommonSight\Sdk\Plugin\SourceDescription;

/**
 * The real chain from the sources to the layer state for tests, with every input/output in memory: the plugins answer
 * from their own recordings (plugins/<group>/<id>/tests/responses/); responses given to the HTTP client of the test win (edge
 * cases, failures).
 */
final class RefreshChain
{
    public const RELEASE = 'test';

    public readonly SourcePlans $plans;
    public readonly InMemoryStates $states;
    public readonly InMemorySnapshots $snapshots;
    public readonly InMemoryOutcomes $outcomes;
    public readonly InMemoryHealth $health;
    public readonly InMemoryRunMarker $marker;
    public readonly FakeLockFactory $locks;
    public readonly SourceBatch $batch;
    public readonly LayerComposer $composer;
    public readonly SourceRefresh $refresh;
    public readonly DueSources $due;

    /** @param array<string, mixed> $config sections of config.php besides the paths */
    public function __construct(FakeHttpClient $http, public readonly Logger $log, public readonly FakeClock $clock, array $config = [])
    {
        $config = Config::fromArray(['paths' => ['data' => '/tmp/x', 'state' => '/tmp/x', 'cache' => sys_get_temp_dir() . '/commonsight-test-cache', 'locks' => '/tmp/x', 'logs' => '/tmp/x']] + $config);
        $data = Fixtures::generated();
        $loader = new PluginLoader($data, dirname(Fixtures::backendDir()) . '/plugins', $config, new SafeXmlReader(), new SystemClock());
        $sources = $loader->sources(
            static fn(SourceDescription $description, string $dir): HttpClient => new SourceHttpClient(new TestResponsesFirst($http, new ReplayHttpClient(self::cassettes($dir))), $description->id),
            static fn(SourceDescription $description): Secrets => new Secrets(array_fill_keys($description->secrets, 'recorded')),
            static fn(SourceDescription $description): PluginStore => new InMemoryPluginStore(),
        );
        $targets = [];
        foreach ($data->layerMetas() as $meta) {
            foreach ($meta->scopes() as $scope) {
                $targets[] = new LayerTarget($meta->id, $scope);
            }
        }
        $this->plans = (new SourcePlanner($config))->plans($sources, $targets);
        [$this->states, $this->snapshots, $this->outcomes, $this->health, $this->marker, $this->locks] = [new InMemoryStates(), new InMemorySnapshots(), new InMemoryOutcomes(), new InMemoryHealth(), new InMemoryRunMarker(), new FakeLockFactory()];
        $selection = new SourceSelection($config->sourceSwitches);
        $archive = new OutcomeArchive($this->outcomes, self::RELEASE);
        $healthRecorder = new HealthRecorder($this->health, new BackoffPolicy(), new FixedRandom(0.0), self::RELEASE);
        $this->due = new DueSources($healthRecorder, new DueSourcePolicy(), self::RELEASE);
        $this->refresh = new SourceRefresh($this->locks, new PluginRunner($selection, new DriftPolicy(), new FixedMemory(), $log), $archive, $healthRecorder, $this->marker, $log);
        $layers = new LayerLoader($data, dirname(Fixtures::backendDir()) . '/plugins', $config);
        $this->composer = new LayerComposer($layers->catalog(array_map(static fn($s): SourceDescription => $s->description, $sources)), $archive, new SnapshotAssembler(new IssueCollector()));
        $publisher = new LayerPublisher(
            $this->locks,
            $this->states,
            $this->composer,
            new LayerSchedule($healthRecorder, new StalenessPolicy($config->staleFactor), $selection, $config->lanes->cadences()),
            new ResultRecorder(new ContentHash(), $this->snapshots, $this->states),
            $log,
        );
        $this->batch = new SourceBatch($this->refresh, $publisher, $this->plans, $healthRecorder, $clock);
    }

    /** Runs every source of the layer once, regardless of its interval, and assembles the layer from their outcomes. */
    public function refresh(LayerTarget $target, UtcInstant $now): Assembly
    {
        $this->clock->now = $now;
        $plans = $this->plans->of($target);
        foreach ($plans as $plan) {
            $this->refresh->refresh($plan, $now);
        }

        return $this->composer->compose($target, $plans, $now);
    }

    public function laneRunner(): LaneRunner
    {
        return new LaneRunner($this->locks, $this->plans, $this->due, $this->batch, $this->clock);
    }

    /** @param array<string, int|null> $fallbackSec fallback budget per lane */
    public function fallbackTrigger(InMemoryTriggerStamp $stamps, array $fallbackSec = ['fast' => 60, 'heavy' => 120, 'slow' => null]): FallbackTrigger
    {
        return new FallbackTrigger(new StalenessPolicy(2.5), $stamps, $this->locks, $this->plans, $this->due, $this->batch, $fallbackSec, 60);
    }

    /** @return list<Cassette> the recordings of a plugin for all its scopes */
    private static function cassettes(string $pluginDir): array
    {
        $cassettes = [];
        foreach (glob($pluginDir . '/tests/responses/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $cassette = Cassette::read($dir);
            if ($cassette !== null) {
                $cassettes[] = $cassette;
            }
        }

        return $cassettes;
    }
}
