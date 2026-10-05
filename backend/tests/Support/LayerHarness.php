<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Domain\Snapshot\Assembly;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Snapshot;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use PHPUnit\Framework\Assert;

/** Runs a layer through the real chain (catalog, sources, parser, mapper, pipeline, assembler) with responses from recordings. */
final class LayerHarness
{
    public readonly FakeHttpClient $http;
    public readonly CollectingLogger $log;

    /** @param array<string, bool> $switches */
    public function __construct(private readonly array $switches = [])
    {
        $this->http = new FakeHttpClient();
        $this->log = new CollectingLogger();
    }

    /** Response for all requests whose URL starts like this, from a test case of a plugin (plugins/<group>/<source>/tests/cases/<file>). */
    public function respond(string $urlPrefix, string $source, string $file): self
    {
        $this->http->respond($urlPrefix, Fixtures::caseBody($source, $file));

        return $this;
    }

    /**
     * The snapshot of the layer, valid against the contract and stored as contract/fixtures/<layer>-<scope>.json for the
     * frontend tests; without errors besides the expected issue.belowExpected of shortened recordings (F-18).
     */
    public function record(LayerId $layer, Scope $scope, string $now): Snapshot
    {
        $snapshot = $this->snapshot($layer, $scope, $now);
        SnapshotContract::record($layer->value . '-' . $scope->value, $snapshot);
        $unexpected = array_filter($snapshot->issues, static fn($issue): bool => $issue->key !== 'issue.belowExpected');
        Assert::assertSame([], array_values($unexpected), json_encode($snapshot->issues, JSON_UNESCAPED_UNICODE) ?: '');
        Assert::assertNotSame(FeedStatus::Error, $snapshot->status);

        return $snapshot;
    }

    /** The snapshot of the layer; fails the test when the layer cannot be assembled. */
    public function snapshot(LayerId $layer, Scope $scope, string $now): Snapshot
    {
        $assembly = $this->run($layer, $scope, $now);
        Assert::assertNotNull($assembly->snapshot, 'Layer failed: ' . ($assembly->failure->key ?? '') . ' ' . json_encode($this->log->entries));

        return $assembly->snapshot;
    }

    public function run(LayerId $layer, Scope $scope, string $now): Assembly
    {
        $sources = array_map(static fn(bool $enabled): array => ['enabled' => $enabled], $this->switches);
        $chain = new RefreshChain($this->http, $this->log, new FakeClock(UtcInstant::fromIso($now)), ['sources' => $sources]);

        return $chain->refresh(new LayerTarget($layer, $scope), UtcInstant::fromIso($now));
    }
}
