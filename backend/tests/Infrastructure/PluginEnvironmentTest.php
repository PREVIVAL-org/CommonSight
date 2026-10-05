<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Http\SourceHttpClient;
use CommonSight\Infrastructure\Plugin\FilePluginData;
use CommonSight\Infrastructure\Plugin\FilePluginStore;
use CommonSight\Infrastructure\Runtime\SystemMemory;
use CommonSight\Infrastructure\Storage\AtomicFile;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Tests\Support\FakeClock;
use CommonSight\Tests\Support\FakeHttpClient;
use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/** The services the core lends to a plugin (concept: sources as plugins, 4.2). */
final class PluginEnvironmentTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = TempDir::create('plugin-environment');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->dir);
    }

    public function testStoreKeepsValuesUntilTheyExpire(): void
    {
        $clock = new FakeClock(UtcInstant::fromIso('2026-10-03T12:00:00Z'));
        $store = new FilePluginStore($this->dir . '/cache/plugins/demo', new AtomicFile(), $clock);

        $store->write('etag https://api.example/a?b=1', '"v1"', 60);
        $store->write('cursor', '42');
        self::assertSame('"v1"', $store->read('etag https://api.example/a?b=1'));

        $clock->now = UtcInstant::fromIso('2026-10-03T12:01:00Z');
        self::assertNull($store->read('etag https://api.example/a?b=1'), 'expired');
        self::assertSame('42', $store->read('cursor'), 'without lifetime');

        $store->delete('cursor');
        self::assertNull($store->read('cursor'));
        self::assertNull($store->read('never written'));
    }

    public function testPluginDataReadsOnlyJsonFromItsOwnFolder(): void
    {
        TempDir::write($this->dir . '/data/stations.json', '[{"id":"A1"}]');
        $data = new FilePluginData($this->dir . '/data', Fixtures::generated());

        self::assertSame('A1', $data->readOwnJson('stations.json')->get(0, 'id')->string());
        self::assertNotEmpty($data->cities());
        self::assertNotEmpty($data->borderPlaces());

        $this->expectException(\RuntimeException::class);
        $data->readOwnJson('../../etc/passwd.json');
    }

    public function testMapsStableOfficialCodesToRegions(): void
    {
        $codes = (new FilePluginData($this->dir . '/data', Fixtures::generated()))->regionCodes();

        self::assertSame('DE-BY', $codes->regionFor('ags', '09162000')?->value, 'municipality key of Munich: only the state prefix counts');
        self::assertSame('DE-BY', $codes->regionFor('ags', '09')?->value);
        self::assertSame('AT-7', $codes->regionFor('gkz', '70101')?->value, 'Gemeindekennzahl of Innsbruck');
        self::assertSame('CH-ZH', $codes->regionFor('bfs', '01')?->value);
        self::assertSame('CH-JU', $codes->regionFor('bfs', '26')?->value);
        self::assertSame('DE-BY', $codes->regionFor('iso', 'de-by')?->value);
        self::assertNull($codes->regionFor('ags', '99000000'), 'unknown state key');
        self::assertNull($codes->regionFor('gkz', 'X1'), 'not a code');

        $this->expectException(\InvalidArgumentException::class);
        $codes->regionFor('nuts', 'DE21');
    }

    public function testMissingDataFileIsAnError(): void
    {
        $this->expectException(\RuntimeException::class);

        (new FilePluginData($this->dir . '/data', Fixtures::generated()))->readOwnJson('missing.json');
    }

    public function testHttpClientMarksEveryRequestWithTheSourceOfThePlugin(): void
    {
        $http = new FakeHttpClient();
        $client = new SourceHttpClient($http, 'demo');

        $client->fetchAll([
            (new HttpRequest('https://api.example/a', 'application/json', 'pretending-to-be-another'))->withSecretHeader('X-Api-Key', 'k'),
            new HttpRequest('https://api.example/b', 'application/json', 'demo', 'b'),
        ]);

        self::assertSame(['demo', 'demo'], array_map(static fn(HttpRequest $r): string => $r->sourceId, $http->requests));
        self::assertSame(['X-Api-Key'], $http->requests[0]->secrets, 'headers and secrets are kept');
        self::assertSame('b', $http->requests[1]->key);
    }

    public function testMemoryLimitIsReadInAllNotations(): void
    {
        self::assertSame(256 * 1024 * 1024, SystemMemory::bytes('256M'));
        self::assertSame(1024 ** 3, SystemMemory::bytes('1G'));
        self::assertSame(512 * 1024, SystemMemory::bytes('512k'));
        self::assertSame(131072, SystemMemory::bytes('131072'));
        self::assertNull(SystemMemory::bytes('-1'));
    }
}
