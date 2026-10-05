<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Recording\Cassette;
use CommonSight\Infrastructure\Recording\RecordingHttpClient;
use CommonSight\Infrastructure\Recording\ReplayHttpClient;
use CommonSight\Infrastructure\Recording\ResponseTrimmer;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Tests\Support\FakeHttpClient;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/** Recording and replaying the API responses of a plugin (concept: sources as plugins, P4; F-15). */
final class RecordingTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = TempDir::create('recording');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->dir);
    }

    public function testRecordsWithoutSecretsAndReplaysWithAnyKey(): void
    {
        $api = new FakeHttpClient(['https://api.example.org/' => '{"value":1}']);
        $recorder = new RecordingHttpClient($api, static fn(string $body): string => $body);
        $recorder->fetchAll([
            (new HttpRequest('https://api.example.org/v1/a', 'application/json', 'demo'))->withSecretQuery('apikey', 'real-key'),
            HttpRequest::postJson('https://api.example.org/v1/search', '{"from":"2026-10-03"}', 'demo'),
            new HttpRequest('https://api.example.org/missing', 'application/json', 'demo'),
        ]);
        (new Cassette(UtcInstant::fromIso('2026-10-03T12:00:00Z'), $recorder->exchanges))->write($this->dir . '/AT');

        self::assertStringNotContainsString('real-key', (string) file_get_contents($this->dir . '/AT/index.json'));
        self::assertCount(1, glob($this->dir . '/AT/[0-9][0-9][0-9].*') ?: [], 'identical bodies share one file');

        $cassette = Cassette::read($this->dir . '/AT');
        self::assertNotNull($cassette);
        self::assertSame('2026-10-03T12:00:00Z', $cassette->recordedAt->toIso());
        $replay = new ReplayHttpClient([$cassette]);
        [$a, $search, $other] = $replay->fetchAll([
            (new HttpRequest('https://api.example.org/v1/a', 'application/json', 'demo'))->withSecretQuery('apikey', 'test-key'),
            HttpRequest::postJson('https://api.example.org/v1/search', '{"from":"2026-10-03"}', 'demo'),
            HttpRequest::postJson('https://api.example.org/v1/search', '{"from":"2026-10-04"}', 'demo'),
        ]);
        self::assertInstanceOf(HttpResponse::class, $a);
        self::assertSame('{"value":1}', $a->body);
        self::assertInstanceOf(HttpResponse::class, $search);
        self::assertInstanceOf(HttpFailure::class, $other, 'another body is another request');
    }

    public function testAMissingRecordingIsNoCassette(): void
    {
        self::assertNull(Cassette::read($this->dir . '/CH'));
    }

    public function testTrimsLongResponsesToTheirFirstItems(): void
    {
        $trimmer = new ResponseTrimmer(2);
        $features = ['type' => 'FeatureCollection', 'features' => [
            ['geometry' => ['coordinates' => [10.0, 47.0]]],
            ['geometry' => ['coordinates' => [21.0, 52.0]]],
            ['geometry' => ['coordinates' => [11.0, 48.0]]],
            ['geometry' => ['coordinates' => [12.0, 49.0]]],
        ]];

        $trimmed = json_decode($trimmer->trim((string) json_encode($features)), true);
        self::assertIsArray($trimmed);
        self::assertEquals([[10, 47], [11, 48]], array_column(array_column((array) $trimmed['features'], 'geometry'), 'coordinates'), 'east of 17.5° E dropped, then the first two');
        self::assertSame('[1,2]', str_replace([' ', "\n"], '', $trimmer->trim('[1,2,3,4]')));
        self::assertSame(2, substr_count($trimmer->trim('<rss><channel><item>a</item><item>b</item><item>c</item></channel></rss>'), '<item>'));
        self::assertSame('plain text', $trimmer->trim('plain text'));
    }
}
