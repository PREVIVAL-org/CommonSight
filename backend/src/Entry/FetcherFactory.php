<?php

declare(strict_types=1);

namespace CommonSight\Entry;

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
use CommonSight\Domain\Snapshot\ContentHash;
use CommonSight\Domain\Snapshot\IssueCollector;
use CommonSight\Domain\Snapshot\SnapshotAssembler;
use CommonSight\Domain\Source\DriftPolicy;
use CommonSight\Domain\Source\SourceSelection;
use CommonSight\Infrastructure\Clock\SystemClock;
use CommonSight\Infrastructure\Clock\SystemRandom;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Http\CurlHandleFactory;
use CommonSight\Infrastructure\Http\CurlHttpClient;
use CommonSight\Infrastructure\Lock\FileLockFactory;
use CommonSight\Infrastructure\Lock\TriggerStampFile;
use CommonSight\Infrastructure\Log\JsonLineLogger;
use CommonSight\Infrastructure\Runtime\SystemMemory;
use CommonSight\Infrastructure\Storage\AtomicFile;
use CommonSight\Infrastructure\Storage\LayerStateCodec;
use CommonSight\Infrastructure\Storage\OutcomeFile;
use CommonSight\Infrastructure\Storage\RunMarkerFile;
use CommonSight\Infrastructure\Storage\SnapshotFileWriter;
use CommonSight\Infrastructure\Storage\SourceHealthFile;
use CommonSight\Infrastructure\Storage\StateFileReader;
use CommonSight\Infrastructure\Storage\StateFileWriter;
use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Model\Layer\LayerMeta;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Port\Logger;
use CommonSight\Port\SourceHealthStore;
use CommonSight\Port\StateReader;
use CommonSight\Sdk\Plugin\SourceDescription;

/** Wires up the fetcher's objects; only here is it stated which class implements which interface (Architecture 1.3.3). */
final class FetcherFactory
{
    private ?Logger $logger = null;
    private ?SourcePlans $plans = null;
    private ?SourceBatch $batch = null;
    private ?HealthRecorder $health = null;

    /** @param float $deadline end of this process's time budget (Unix time) */
    public function __construct(
        private readonly Config $config,
        private readonly GeneratedData $data,
        private readonly string $process,
        private readonly float $deadline,
    ) {}

    public function laneRunner(): LaneRunner
    {
        return new LaneRunner(new FileLockFactory($this->config->paths->locks), $this->plans(), $this->dueSources(), $this->batch(), new SystemClock());
    }

    public function fallbackTrigger(): FallbackTrigger
    {
        return new FallbackTrigger(
            new StalenessPolicy($this->config->staleFactor),
            new TriggerStampFile($this->config->paths->locks),
            new FileLockFactory($this->config->paths->locks),
            $this->plans(),
            $this->dueSources(),
            $this->batch(),
            $this->config->lanes->fallbackSeconds(),
            $this->config->triggerMinIntervalSec,
        );
    }

    /** Runs sources and reassembles their layers; for runs by hand. */
    public function batch(): SourceBatch
    {
        return $this->batch ??= $this->createBatch();
    }

    /** All sources in all their scopes with lane and effective interval, and all layers. */
    public function plans(): SourcePlans
    {
        return $this->plans ??= (new SourcePlanner($this->config))->plans($this->plugins()->operational($this->http()), $this->targets());
    }

    public function stateReader(): StateReader
    {
        return new StateFileReader($this->config->paths->state, new AtomicFile(), new LayerStateCodec());
    }

    public function healthStore(): SourceHealthStore
    {
        return new SourceHealthFile($this->config->paths->state, new AtomicFile());
    }

    public function logger(): Logger
    {
        return $this->logger ??= new JsonLineLogger($this->config->paths->logs . '/fetcher.log', $this->process);
    }

    /** @return list<LayerTarget> all layers in all their scopes */
    public function targets(): array
    {
        $targets = [];
        foreach ($this->layerMetas() as $meta) {
            foreach ($meta->scopes() as $scope) {
                $targets[] = new LayerTarget($meta->id, $scope);
            }
        }

        return $targets;
    }

    /** @return list<LayerMeta> */
    public function layerMetas(): array
    {
        return $this->data->layerMetas();
    }

    /** @return list<SourceDescription> the descriptions of all source plugins */
    public function sourceDescriptions(): array
    {
        return $this->plugins()->descriptions();
    }

    /**
     * The release that runs: its outcomes are readable only by itself (OutcomeArchive), because classes may change with
     * an update. In an installation each release has its own folder.
     */
    private function release(): string
    {
        return substr(sha1((string) realpath(dirname(__DIR__, 2))), 0, 12);
    }

    public function layers(): LayerLoader
    {
        return new LayerLoader($this->data, PluginLoader::pluginsDir(dirname(__DIR__, 2)), $this->config);
    }

    private function plugins(): PluginLoader
    {
        return new PluginLoader($this->data, PluginLoader::pluginsDir(dirname(__DIR__, 2)), $this->config, new SafeXmlReader(), new SystemClock());
    }

    private function http(): CurlHttpClient
    {
        return new CurlHttpClient($this->plugins()->httpLimits(), new CurlHandleFactory(), $this->deadline);
    }

    private function dueSources(): DueSources
    {
        return new DueSources($this->healthRecorder(), new DueSourcePolicy(), $this->release());
    }

    private function healthRecorder(): HealthRecorder
    {
        return $this->health ??= new HealthRecorder($this->healthStore(), new BackoffPolicy(), new SystemRandom(), $this->release());
    }

    private function createBatch(): SourceBatch
    {
        $locks = new FileLockFactory($this->config->paths->locks);
        $files = new AtomicFile();
        $outcomes = new OutcomeArchive(new OutcomeFile($this->config->paths->cache, $files), $this->release());
        $selection = new SourceSelection($this->config->sourceSwitches);
        $runner = new PluginRunner($selection, new DriftPolicy($this->config->driftMaxRejectedShare), new SystemMemory(), $this->logger());
        $refresh = new SourceRefresh($locks, $runner, $outcomes, $this->healthRecorder(), new RunMarkerFile($this->config->paths->state, $files), $this->logger());
        $publisher = new LayerPublisher(
            $locks,
            $this->stateReader(),
            new LayerComposer($this->layers()->catalog($this->sourceDescriptions()), $outcomes, new SnapshotAssembler(new IssueCollector())),
            new LayerSchedule($this->healthRecorder(), new StalenessPolicy($this->config->staleFactor), $selection, $this->config->lanes->cadences()),
            new ResultRecorder(new ContentHash(), new SnapshotFileWriter($this->config->paths->data, $files), new StateFileWriter($this->config->paths->state, $files, new LayerStateCodec())),
            $this->logger(),
        );

        return new SourceBatch($refresh, $publisher, $this->plans(), $this->healthRecorder(), new SystemClock());
    }

}
