<?php

declare(strict_types=1);

namespace CommonSight\Tests\Contract;

use CommonSight\Model\FeedStatus;
use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use CommonSight\Tests\Support\SnapshotContract;
use PHPUnit\Framework\TestCase;

/**
 * How the core assembles a layer when its sources fail, are switched off or bring nothing (Q-01, Q-02, F-17), with
 * real layers and the recordings of their sources. What a layer itself shows is tested in its package
 * (plugins/layers/<layer>/tests/), every layer package passes the generic check (LayerContractTest).
 */
final class LayerAssemblyTest extends TestCase
{
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';
    private const CASES_NOW = '2026-09-28T12:00:00Z';

    /** Q-01: if one of several sources fails, the layer stays partial with the remaining data. */
    public function testPartialWhenOneSourceFails(): void
    {
        $harness = (new LayerHarness())
            ->respond('https://maps.dwd.de/', 'dwd-warnings', 'de-cases.json')
            ->respond('https://warnung.bund.de/api31/mowas/', 'bbk-mowas', 'de.json')
            ->respond('https://warnung.bund.de/api31/warnings/', 'bbk-mowas', 'warning.geojson');
        $harness->http->respond('https://maps.dwd.de/', FailureKind::ServerError);

        $snapshot = $harness->snapshot(LayerId::from('warnings'), Scope::DE, self::CASES_NOW);

        SnapshotContract::assertValid($snapshot);
        self::assertSame(FeedStatus::Partial, $snapshot->status);
        self::assertSame('issue.sourceFailed', $snapshot->issues[0]->key);
        self::assertNotEmpty($snapshot->items);
    }

    /** F-17: disabled sole source -> setup, visible as the cause. */
    public function testDisabledSourceBecomesSetup(): void
    {
        $snapshot = (new LayerHarness(['ehyd' => false]))->snapshot(LayerId::from('water'), Scope::AT, self::MEASURED_NOW);

        SnapshotContract::assertValid($snapshot);
        self::assertSame(FeedStatus::Setup, $snapshot->status);
        self::assertSame('issue.sourceDisabled', $snapshot->issues[0]->key);
    }

    /** Q-02: a measurement source without usable items counts as failed. */
    public function testMeasurementSourceWithoutItemsFails(): void
    {
        $harness = new LayerHarness();
        $harness->http->respond('https://ehyd.gv.at/', '{"type":"FeatureCollection","features":[]}');

        $assembly = $harness->run(LayerId::from('water'), Scope::AT, self::MEASURED_NOW);

        self::assertNull($assembly->snapshot);
        self::assertSame('error.noUsableItems', $assembly->failure?->key);
    }
}
