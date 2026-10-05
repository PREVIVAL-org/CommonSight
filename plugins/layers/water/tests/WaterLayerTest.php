<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WaterLayer\Tests;

use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer water: gauges of the countries and of the border zone with their flood stages (Q-WA-*). */
final class WaterLayerTest extends TestCase
{
    /** Shortly after the recordings of the sources (tests/responses of each source plugin). */
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';
    /** Recordings of the border zone's gauges and probes and of the Austrian radiation early warning system. */
    private const BORDER_NOW = '2026-10-02T15:30:00Z';

    /** @return iterable<string, array{Scope, string}> */
    public static function scopes(): iterable
    {
        yield 'DE' => [Scope::DE, self::MEASURED_NOW];
        yield 'AT' => [Scope::AT, self::MEASURED_NOW];
        yield 'CH' => [Scope::CH, self::MEASURED_NOW];
        yield 'border' => [Scope::Border, self::BORDER_NOW];
    }

    /** The recordings of the sources give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope, string $now): void
    {
        (new LayerHarness())->record(LayerId::from('water'), $scope, $now);
    }

    /** Border zone: gauges of the neighbouring countries, only within the border radius of DACH (vicinityKm, ADR 0038). */
    public function testBorderZoneGauges(): void
    {
        $water = (new LayerHarness())->snapshot(LayerId::from('water'), Scope::Border, self::BORDER_NOW);
        $byCountry = [];
        foreach ($water->items as $item) {
            $common = $item->common()->toArray();
            $byCountry[$common['country']][] = $item;
            self::assertNotSame([], $common['near']['countries'], 'only gauges within the border radius');
            self::assertSame([], $common['regionIds']);
        }
        $keys = array_keys($byCountry);
        sort($keys);
        self::assertSame(['CZ', 'FR', 'NL', 'PL'], $keys, "Hub'Eau, Rijkswaterstaat, ČHMÚ, IMGW-PIB");
        $czech = $byCountry['CZ'][0];
        self::assertInstanceOf(MeasurementItem::class, $czech);
        self::assertStringStartsWith('SPA ', (string) $czech->assessment->sourceValue, 'flood stages of ČHMÚ');
        $dutch = $byCountry['NL'][0];
        self::assertInstanceOf(MeasurementItem::class, $dutch);
        self::assertSame('source.rijkswaterstaat.reference.nap', $dutch->reference->key);
    }

    /** PEGELONLINE: canal gauges report above sea level (m+NN), the others above the gauge zero (cm). */
    public function testGermanGaugesNameTheirReference(): void
    {
        $water = (new LayerHarness())->snapshot(LayerId::from('water'), Scope::DE, self::MEASURED_NOW);
        $references = [];
        foreach ($water->items as $item) {
            if ($item instanceof MeasurementItem) {
                $references[$item->unit][$item->reference->key] = true;
            }
        }
        self::assertSame(['reference.seaLevel'], array_keys($references['m+NN'] ?? []));
        self::assertSame(['reference.gaugeZero'], array_keys($references['cm'] ?? []));
    }
}
