<?php

declare(strict_types=1);

namespace CommonSight\Tests\Entry;

use CommonSight\Config\Paths;
use CommonSight\Entry\Housekeeping;
use CommonSight\Infrastructure\Storage\SnapshotCleaner;
use CommonSight\Tests\Support\InMemoryStates;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/** Housekeeping removes temporary files left by a process that died, also in the stores of the plugins (5.2). */
final class HousekeepingTest extends TestCase
{
    public function testRemovesOldTemporaryFilesDownToThePluginStores(): void
    {
        $root = TempDir::create('housekeeping');
        try {
            $now = 100_000;
            foreach (['cache/.tmp-a', 'cache/sources/.tmp-b', 'cache/plugins/geosphere-warnings/.tmp-c', 'cache/plugins/geosphere-warnings/.tmp-fresh'] as $file) {
                TempDir::write($root . '/' . $file, 'x');
                touch($root . '/' . $file, $file === 'cache/plugins/geosphere-warnings/.tmp-fresh' ? $now - 60 : $now - 7200);
            }
            foreach (['data', 'state', 'logs'] as $dir) {
                mkdir($root . '/' . $dir);
            }
            $paths = new Paths($root . '/data', $root . '/state', $root . '/cache', $root . '/locks', $root . '/logs');

            $result = (new Housekeeping($paths, new SnapshotCleaner($root . '/data', new InMemoryStates())))->run([], $now);

            self::assertSame(3, $result['temporary']);
            self::assertFileExists($root . '/cache/plugins/geosphere-warnings/.tmp-fresh', 'possibly still being written');
            self::assertFileDoesNotExist($root . '/cache/plugins/geosphere-warnings/.tmp-c');
        } finally {
            TempDir::remove($root);
        }
    }
}
