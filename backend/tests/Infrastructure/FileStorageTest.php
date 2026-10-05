<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Lock\FileLockFactory;
use CommonSight\Infrastructure\Lock\TriggerStampFile;
use CommonSight\Infrastructure\Storage\AtomicFile;
use CommonSight\Infrastructure\Storage\LayerStateCodec;
use CommonSight\Infrastructure\Storage\OutcomeFile;
use CommonSight\Infrastructure\Storage\RunMarkerFile;
use CommonSight\Infrastructure\Storage\SnapshotCleaner;
use CommonSight\Infrastructure\Storage\SnapshotFileWriter;
use CommonSight\Infrastructure\Storage\SourceHealthFile;
use CommonSight\Infrastructure\Storage\StateFileReader;
use CommonSight\Infrastructure\Storage\StateFileWriter;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Msg;
use CommonSight\Model\State\LastError;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use PHPUnit\Framework\TestCase;

/** F-03 (atomic, precompressed), F-04 (locks), Architecture 4.6, 4.7, 4.9, 6.3 with a real file system. */
final class FileStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cs-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function testWritesSnapshotWithCompressedVariants(): void
    {
        $path = (new SnapshotFileWriter($this->dir, new AtomicFile()))->write(LayerId::from('warnings'), Scope::AT, '3f9a1c2e', '{"a":1}');

        self::assertSame('AT/warnings.3f9a1c2e.json', $path);
        self::assertSame('{"a":1}', file_get_contents($this->dir . '/' . $path));
        self::assertSame('{"a":1}', gzdecode((string) file_get_contents($this->dir . '/' . $path . '.gz')));
        if (function_exists('brotli_uncompress')) {
            self::assertSame('{"a":1}', brotli_uncompress((string) file_get_contents($this->dir . '/' . $path . '.br')));
        }
        self::assertSame([], glob($this->dir . '/AT/.tmp-*'), 'no temporary files');
        self::assertSame('0644', substr(sprintf('%o', fileperms($this->dir . '/' . $path)), -4));
    }

    public function testStateFileRoundTripAndBrokenFile(): void
    {
        $state = new LayerState(LayerId::from('water'), Scope::CH, 'abcdef12', 'CH/water.abcdef12.json', FeedStatus::Partial, UtcInstant::fromIso('2026-09-28T10:00:00Z'), UtcInstant::fromIso('2026-09-28T10:05:00Z'), null, 7, [new Msg('issue.missingGeometry', ['count' => 2])], new LastError(UtcInstant::fromIso('2026-09-28T10:10:00Z'), new Msg('error.allSourcesFailed')), 1, null, 600, UtcInstant::fromIso('2026-09-28T10:30:00Z'));
        (new StateFileWriter($this->dir, new AtomicFile(), new LayerStateCodec()))->write($state);
        $reader = new StateFileReader($this->dir, new AtomicFile(), new LayerStateCodec());

        self::assertEquals($state, $reader->read(LayerId::from('water'), Scope::CH));
        self::assertNull($reader->read(LayerId::from('water'), Scope::AT));
        file_put_contents($this->dir . '/CH/water.json', '{"layer":');
        self::assertNull($reader->read(LayerId::from('water'), Scope::CH), 'broken file counts as missing');
    }

    public function testSourceFilesRoundTrip(): void
    {
        $files = new AtomicFile();
        $health = new SourceHealth('pegelonline', Scope::DE, UtcInstant::fromIso('2026-09-28T10:00:00Z'), UtcInstant::fromIso('2026-09-28T09:45:00Z'), 2, UtcInstant::fromIso('2026-09-28T10:02:00Z'), 'timeout', 0, 840, 'abc123');
        $store = new SourceHealthFile($this->dir, $files);
        $store->write($health);

        self::assertEquals($health, $store->read('pegelonline', Scope::DE));
        self::assertNull($store->read('pegelonline', Scope::AT));
        file_put_contents($this->dir . '/sources/pegelonline-DE.json', '{');
        self::assertNull($store->read('pegelonline', Scope::DE), 'broken file counts as missing');

        $outcomes = new OutcomeFile($this->dir, $files);
        $outcomes->write('open-meteo-weather-border', 'payload');
        self::assertSame('payload', $outcomes->read('open-meteo-weather-border'));
        self::assertNull($outcomes->read('usgs-DE'));

        $marker = new RunMarkerFile($this->dir, $files);
        $marker->start('heavy', 'usgs-border', \CommonSight\Model\Value\UtcInstant::fromIso('2026-09-28T12:00:00Z'));
        self::assertSame('usgs-border', $marker->unfinished('heavy'));
        self::assertSame('2026-09-28T12:00:00Z', $marker->startedAt('heavy')?->toIso());
        self::assertNull($marker->unfinished('fast'));
        $marker->finish('heavy');
        self::assertNull($marker->unfinished('heavy'));
    }

    public function testOutcomeKeysCannotLeaveTheirFolder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new OutcomeFile($this->dir, new AtomicFile()))->write('../state/x-DE', 'payload');
    }

    public function testLockIsExclusiveUntilReleased(): void
    {
        $locks = new FileLockFactory($this->dir . '/locks');
        $first = $locks->acquire('layer-AT-warnings');

        self::assertNotNull($first);
        self::assertNull($locks->acquire('layer-AT-warnings'));
        self::assertNotNull($locks->acquire('layer-DE-warnings'));
        $first->release();
        self::assertNotNull($locks->acquire('layer-AT-warnings'));
    }

    /** A layer assembly waits briefly for one in progress, but not forever. */
    public function testWaitingForALockGivesUpAfterItsTime(): void
    {
        $locks = new FileLockFactory($this->dir . '/locks');
        $held = $locks->acquire('layer-AT-water');
        self::assertNotNull($held);

        $started = microtime(true);
        self::assertNull($locks->acquireWaiting('layer-AT-water', 0.2));
        self::assertGreaterThanOrEqual(0.2, microtime(true) - $started);
        $held->release();
        $free = $locks->acquireWaiting('layer-AT-water', 5.0);
        self::assertNotNull($free, 'a free lock comes at once');
        $free->release();
    }

    public function testTriggerStampEnforcesMinimumInterval(): void
    {
        $stamps = new TriggerStampFile($this->dir . '/locks-not-yet-created');

        self::assertTrue($stamps->claim('trigger-AT-warnings', UtcInstant::fromTimestamp(1000), 60));
        self::assertFalse($stamps->claim('trigger-AT-warnings', UtcInstant::fromTimestamp(1059), 60));
        self::assertTrue($stamps->claim('trigger-AT-warnings', UtcInstant::fromTimestamp(1060), 60));
    }

    public function testCleanerKeepsCurrentAndThreeLatestAndRespectsGracePeriod(): void
    {
        $now = 100_000;
        mkdir($this->dir . '/AT');
        foreach (['aaaaaaa1' => 1000, 'aaaaaaa2' => 2000, 'aaaaaaa3' => 90_000, 'aaaaaaa4' => 99_950, 'aaaaaaa5' => 99_990] as $version => $mtime) {
            $file = $this->dir . '/AT/warnings.' . $version . '.json';
            file_put_contents($file, '{}');
            file_put_contents($file . '.gz', '');
            touch($file, $mtime);
        }
        $states = new \CommonSight\Tests\Support\InMemoryStates();
        $states->write(new LayerState(LayerId::from('warnings'), Scope::AT, 'aaaaaaa1', 'AT/warnings.aaaaaaa1.json', FeedStatus::Ok, null, null, null, 0, [], null, 0, null));

        $deleted = (new SnapshotCleaner($this->dir, $states))->clean([new LayerTarget(LayerId::from('warnings'), Scope::AT)], $now);

        self::assertSame(1, $deleted, 'aaaaaaa2 was replaced more than 15 min ago; aaaaaaa1 is current');
        self::assertFileDoesNotExist($this->dir . '/AT/warnings.aaaaaaa2.json');
        self::assertFileDoesNotExist($this->dir . '/AT/warnings.aaaaaaa2.json.gz');
        self::assertFileExists($this->dir . '/AT/warnings.aaaaaaa1.json');
    }

    /** Snapshots of a layer that no longer exists (package removed) go after the grace period; nothing else is touched. */
    public function testCleanerRemovesSnapshotsOfRemovedLayers(): void
    {
        $now = 100_000;
        mkdir($this->dir . '/AT');
        foreach (['AT/pollen.aaaaaaa1.json' => 1000, 'AT/pollen.aaaaaaa2.json' => 99_990, 'AT/warnings.bbbbbbb1.json' => 1000, 'AT/notes.json' => 1000] as $name => $mtime) {
            file_put_contents($this->dir . '/' . $name, '{}');
            touch($this->dir . '/' . $name, $mtime);
        }
        file_put_contents($this->dir . '/AT/pollen.aaaaaaa1.json.br', '');
        $states = new \CommonSight\Tests\Support\InMemoryStates();

        $deleted = (new SnapshotCleaner($this->dir, $states))->clean([new LayerTarget(LayerId::from('warnings'), Scope::AT)], $now);

        self::assertSame(1, $deleted);
        self::assertFileDoesNotExist($this->dir . '/AT/pollen.aaaaaaa1.json');
        self::assertFileDoesNotExist($this->dir . '/AT/pollen.aaaaaaa1.json.br');
        self::assertFileExists($this->dir . '/AT/pollen.aaaaaaa2.json', 'within the grace period');
        self::assertFileExists($this->dir . '/AT/warnings.bbbbbbb1.json', 'a layer that exists');
        self::assertFileExists($this->dir . '/AT/notes.json', 'not a snapshot');
    }

    /** A writer that died between the compressed variants and the JSON leaves them behind: removed after the grace period. */
    public function testCleanerRemovesCompressedVariantsWithoutTheirJson(): void
    {
        $now = 100_000;
        mkdir($this->dir . '/AT');
        foreach (['AT/warnings.ccccccc1.json.gz' => 1000, 'AT/warnings.ccccccc1.json.br' => 1000, 'AT/warnings.ccccccc2.json.gz' => 99_990, 'AT/warnings.ccccccc3.json' => 1000, 'AT/warnings.ccccccc3.json.gz' => 1000] as $name => $mtime) {
            file_put_contents($this->dir . '/' . $name, '');
            touch($this->dir . '/' . $name, $mtime);
        }

        $deleted = (new SnapshotCleaner($this->dir, new \CommonSight\Tests\Support\InMemoryStates()))->clean([new LayerTarget(LayerId::from('warnings'), Scope::AT)], $now);

        self::assertSame(2, $deleted);
        self::assertFileDoesNotExist($this->dir . '/AT/warnings.ccccccc1.json.gz');
        self::assertFileDoesNotExist($this->dir . '/AT/warnings.ccccccc1.json.br');
        self::assertFileExists($this->dir . '/AT/warnings.ccccccc2.json.gz', 'within the grace period: its writer may still be at work');
        self::assertFileExists($this->dir . '/AT/warnings.ccccccc3.json.gz', 'its JSON is there');
    }
}
