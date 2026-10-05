<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WeatherLayer\Tests;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer weather: model values at the places of the countries and of the border zone (Q-WE-*). */
final class WeatherLayerTest extends TestCase
{
    /** Shortly after the recordings of the sources (tests/responses of each source plugin). */
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';

    /** @return iterable<string, array{Scope, string}> */
    public static function scopes(): iterable
    {
        yield 'DE' => [Scope::DE, self::MEASURED_NOW];
        yield 'AT' => [Scope::AT, self::MEASURED_NOW];
        yield 'CH' => [Scope::CH, self::MEASURED_NOW];
        yield 'border' => [Scope::Border, self::MEASURED_NOW];
    }

    /** The recordings of the sources give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope, string $now): void
    {
        (new LayerHarness())->record(LayerId::from('weather'), $scope, $now);
    }

    /** Border zone: foreign places with their country and the DACH countries and regions near them, no DACH regions. */
    public function testBorderZonePlacesNameTheirNeighbours(): void
    {
        $snapshot = (new LayerHarness())->snapshot(LayerId::from('weather'), Scope::Border, self::MEASURED_NOW);
        $byId = [];
        foreach ($snapshot->items as $item) {
            $byId[$item->common()->id] = $item->common()->toArray();
        }

        // border-places.json covers the map area (300 km); within the border radius of 200 km (border-zone.json) remain
        // all but Lille and Bologna.
        self::assertCount(73, $byId, 'places from border-places.json up to 200 km');
        self::assertArrayNotHasKey('weather:fr-lille', $byId);
        $strasbourg = $byId['weather:fr-strasbourg'];
        self::assertSame('FR', $strasbourg['country']);
        self::assertSame([], $strasbourg['regionIds']);
        self::assertSame(['DE', 'AT', 'CH'], $strasbourg['near']['countries'], 'countries up to 200 km');
        self::assertContains('DE-BW', $strasbourg['near']['regionIds'], 'regions up to 200 km');
        self::assertNotContains('DE-BE', $strasbourg['near']['regionIds'], 'Berlin is further away');
        $vaduz = $byId['weather:li-vaduz'];
        self::assertSame('LI', $vaduz['country']);
        self::assertContains('AT-8', $vaduz['near']['regionIds']);
        self::assertContains('CH-SG', $vaduz['near']['regionIds']);
        $copenhagen = $byId['weather:dk-kobenhavn'];
        self::assertSame(['DE'], $copenhagen['near']['countries']);
    }
}
