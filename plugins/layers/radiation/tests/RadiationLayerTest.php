<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer\Tests;

use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer radiation: probes of the countries and of the border zone with the display thresholds (Q-RA-*). */
final class RadiationLayerTest extends TestCase
{
    /** Shortly after the recordings of the sources (tests/responses of each source plugin). */
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';
    /** Recordings of the border zone's gauges and probes and of the Austrian radiation early warning system. */
    private const BORDER_NOW = '2026-10-02T15:30:00Z';

    /** @return iterable<string, array{Scope, string}> */
    public static function scopes(): iterable
    {
        yield 'DE' => [Scope::DE, self::MEASURED_NOW];
        yield 'AT' => [Scope::AT, self::BORDER_NOW];
        yield 'CH' => [Scope::CH, self::MEASURED_NOW];
        yield 'border' => [Scope::Border, self::BORDER_NOW];
    }

    /** The recordings of the sources give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope, string $now): void
    {
        (new LayerHarness())->record(LayerId::from('radiation'), $scope, $now);
    }

    /** Border zone: probes of the neighbouring countries (ADR 0038). */
    public function testBorderZoneProbes(): void
    {
        $radiation = (new LayerHarness())->snapshot(LayerId::from('radiation'), Scope::Border, self::BORDER_NOW);
        $countries = array_unique(array_map(static fn($i): ?string => $i->common()->country, $radiation->items));
        sort($countries);
        self::assertSame(['IT', 'PL'], $countries, 'PAA and South Tyrol Agency for Environment');
    }

    /** Austria: probes of the radiation early warning system with positions from the master data. */
    public function testAustrianRadiationComesFromItsOwnNetwork(): void
    {
        $snapshot = (new LayerHarness())->snapshot(LayerId::from('radiation'), Scope::AT, self::BORDER_NOW);
        $braunau = array_values(array_filter($snapshot->items, static fn($i): bool => $i->common()->id === 'odl:AT0012'))[0];

        self::assertInstanceOf(MeasurementItem::class, $braunau);
        self::assertSame('µSv/h', $braunau->unit, 'nSv/h converted');
        self::assertEqualsWithDelta(0.094, $braunau->value, 0.0001);
        self::assertSame(['AT-4'], array_map(static fn($id): string => $id->value, $braunau->common->regionIds));
        self::assertSame('Umweltbundesamt · Strahlenfrühwarnsystem', $snapshot->source);
    }
}
