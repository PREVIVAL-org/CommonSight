<?php

declare(strict_types=1);

namespace CommonSight\Tests\Entry;

use CommonSight\Application\ClientReportIntake;
use CommonSight\Application\StartPageGate;
use CommonSight\Config\Paths;
use CommonSight\Domain\Report\ClientReportParser;
use CommonSight\Entry\ClientReportEndpoint;
use CommonSight\Entry\Housekeeping;
use CommonSight\Infrastructure\Log\ClientReportFile;
use CommonSight\Infrastructure\Storage\SnapshotCleaner;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Auth\AuthProvider;
use CommonSight\Sdk\Auth\AuthRequest;
use CommonSight\Sdk\Auth\Community;
use CommonSight\Tests\Support\CollectingLogger;
use CommonSight\Tests\Support\InMemoryStates;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/**
 * Error reports of the browsers at api/report.php (Architecture 6.6): only from the own site and from visitors the
 * start page admits, only the known fields, a file per day with a size limit, removed after the days to keep.
 */
final class ClientReportTest extends TestCase
{
    private const POST = ['REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_HOST' => 'cs.example.org', 'REQUEST_URI' => '/api/report.php'];

    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = TempDir::create('client-report');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->dir);
    }

    /** @param array<mixed> $fields */
    private static function body(array $fields = []): string
    {
        return (string) json_encode($fields + [
            'kind' => 'map-start',
            'message' => 'Failed to initialize WebGL',
            'stack' => "Error: Failed to initialize WebGL\n    at new Map (commonsight.js:1:2)",
            'release' => '20261011120000-abc1234',
            'env' => ['userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0', 'language' => 'de-AT', 'webgl' => 'none', 'renderer' => null, 'softwareRendering' => null, 'workers' => true, 'canvasReadable' => false, 'pixelRatio' => 1.5, 'viewport' => '1280x720', 'fingerprint' => 'x'],
        ]);
    }

    private function endpoint(?AuthProvider $provider, int $maxBytesPerDay = 2_000_000): ClientReportEndpoint
    {
        return new ClientReportEndpoint(new ClientReportIntake(new StartPageGate($provider, new CollectingLogger()), new ClientReportParser(), new ClientReportFile($this->dir, $maxBytesPerDay)));
    }

    /** @return list<array<string, mixed>> */
    private function logged(): array
    {
        $lines = [];
        foreach (glob($this->dir . '/client-*.log') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $lines[] = (array) json_decode($line, true);
            }
        }

        return $lines;
    }

    public function testAnAdmittedVisitorsReportIsKeptWithTheKnownFieldsOnly(): void
    {
        $provider = new AdmittingProvider(true);

        self::assertSame(204, $this->endpoint($provider)->answer(self::POST, ['wsc_session' => 'session-7f3e'], self::body()));

        $logged = $this->logged();
        self::assertCount(1, $logged);
        self::assertSame(['time', 'kind', 'message', 'source', 'stack', 'release', 'env'], array_keys($logged[0]));
        self::assertSame('map-start', $logged[0]['kind']);
        self::assertStringContainsString("\n    at new Map", (string) $logged[0]['stack'], 'the stack keeps its lines');
        self::assertArrayNotHasKey('fingerprint', (array) $logged[0]['env']);
        self::assertSame(['none', false, 1.5], [$logged[0]['env']['webgl'], $logged[0]['env']['canvasReadable'], $logged[0]['env']['pixelRatio']]);
        self::assertSame(['wsc_session' => 'session-7f3e'], $provider->seen?->cookies);
        self::assertSame('http://cs.example.org/', $provider->seen?->pageUrl, 'the start page, not the endpoint');
        self::assertStringNotContainsString('session-7f3e', (string) json_encode($logged), 'no cookie in the log');
    }

    public function testAVisitorTheStartPageDoesNotAdmitIsRefused(): void
    {
        self::assertSame(403, $this->endpoint(new AdmittingProvider(false))->answer(self::POST, [], self::body()));
        self::assertSame(403, $this->endpoint(new AdmittingProvider(new \RuntimeException('forum database not reachable')))->answer(self::POST, [], self::body()), 'closed when the provider cannot decide');
        self::assertSame([], $this->logged());
    }

    public function testWithoutAProviderReportsAreOpenLikeThePage(): void
    {
        self::assertSame(204, $this->endpoint(null)->answer(self::POST, [], self::body()));
    }

    public function testOnlySmallPostsFromTheOwnSite(): void
    {
        $endpoint = $this->endpoint(null);

        self::assertSame(405, $endpoint->answer(['REQUEST_METHOD' => 'GET'] + self::POST, [], ''));
        self::assertSame(403, $endpoint->answer(['HTTP_SEC_FETCH_SITE' => 'cross-site'] + self::POST, [], self::body()));
        self::assertSame(413, $endpoint->answer(self::POST, [], str_repeat('x', ClientReportEndpoint::MAX_BYTES + 1)));
        self::assertSame(400, $endpoint->answer(self::POST, [], '{"kind":"other","message":"x"}'));
        self::assertSame(400, $endpoint->answer(self::POST, [], 'not json'));
        self::assertSame(404, (new ClientReportEndpoint(null))->answer(self::POST, [], self::body()), 'switched off');
        self::assertSame([], $this->logged());
    }

    public function testTextsAreCutAndControlCharactersRemoved(): void
    {
        $report = (new ClientReportParser())->parse(self::body(['message' => "a\x00b\n" . str_repeat('ü', 600), 'env' => ['viewport' => '1x1; drop', 'pixelRatio' => 1e9, 'webgl' => 'webgl3']]));

        self::assertSame(500, mb_strlen((string) $report['message']));
        self::assertStringStartsWith('a b ü', (string) $report['message']);
        self::assertSame([null, 16.0, null], [$report['env']['viewport'], $report['env']['pixelRatio'], $report['env']['webgl']]);
    }

    public function testAFullDayDropsFurtherReports(): void
    {
        $endpoint = $this->endpoint(null, 100);

        self::assertSame(204, $endpoint->answer(self::POST, [], self::body()));
        self::assertSame(429, $endpoint->answer(self::POST, [], self::body()));
        self::assertCount(1, $this->logged());
    }

    public function testHousekeepingRemovesReportsOlderThanTheDaysToKeep(): void
    {
        $now = UtcInstant::fromIso('2026-10-11T12:00:00Z')->timestamp;
        foreach (['client-2026-09-10.log', 'client-2026-09-11.log', 'client-2026-10-11.log', 'fetcher.log'] as $file) {
            TempDir::write($this->dir . '/logs/' . $file, "{}\n");
        }
        $paths = new Paths($this->dir . '/data', $this->dir . '/state', $this->dir . '/cache', $this->dir . '/locks', $this->dir . '/logs');

        $result = (new Housekeeping($paths, new SnapshotCleaner($this->dir . '/data', new InMemoryStates()), 30))->run([], $now);

        self::assertSame(1, $result['clientReports']);
        self::assertFileDoesNotExist($this->dir . '/logs/client-2026-09-10.log');
        self::assertFileExists($this->dir . '/logs/client-2026-09-11.log', 'exactly 30 days old');
        self::assertFileExists($this->dir . '/logs/fetcher.log');
    }
}

/** A provider with a fixed answer, or a failure; remembers the request it saw. */
final class AdmittingProvider implements AuthProvider
{
    public ?AuthRequest $seen = null;

    public function __construct(private readonly bool|\RuntimeException $answer) {}

    public function admits(AuthRequest $request): bool
    {
        $this->seen = $request;
        if ($this->answer instanceof \RuntimeException) {
            throw $this->answer;
        }

        return $this->answer;
    }

    public function community(AuthRequest $request): Community
    {
        return new Community('Forum');
    }
}
