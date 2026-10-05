<?php

declare(strict_types=1);

namespace CommonSight\Plugin\TrafficLayer\Tests;

use CommonSight\Model\FeedStatus;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use CommonSight\Tests\Support\SnapshotContract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer traffic: motorway warnings and road reports of Germany and Austria; Switzerland without a source yet. */
final class TrafficLayerTest extends TestCase
{
    /** Shortly after the recordings of the sources (tests/responses of each source plugin). */
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';

    /** @return iterable<string, array{Scope}> */
    public static function scopes(): iterable
    {
        yield 'DE' => [Scope::DE];
        yield 'AT' => [Scope::AT];
    }

    /** The recordings of the sources give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope): void
    {
        (new LayerHarness())->record(LayerId::from('traffic'), $scope, self::MEASURED_NOW);
    }

    /** Q-TR-CH-01: without a source in Switzerland the layer is in setup and names what is missing. */
    public function testSwitzerlandIsInSetup(): void
    {
        $snapshot = (new LayerHarness())->snapshot(LayerId::from('traffic'), Scope::CH, self::MEASURED_NOW);

        SnapshotContract::record('traffic-CH', $snapshot);
        self::assertSame(FeedStatus::Setup, $snapshot->status);
    }
}
