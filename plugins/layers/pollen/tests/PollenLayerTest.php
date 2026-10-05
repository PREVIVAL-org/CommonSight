<?php

declare(strict_types=1);

namespace CommonSight\Plugin\PollenLayer\Tests;

use CommonSight\Model\FeedStatus;
use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer pollen: model pollen concentrations at the places of the countries and of the border zone. */
final class PollenLayerTest extends TestCase
{
    /** Shortly after the recordings of the source (plugins/providers/open-meteo-pollen/tests/responses). */
    private const RECORDED_NOW = '2026-10-03T19:45:00Z';

    /** @return iterable<string, array{Scope}> */
    public static function scopes(): iterable
    {
        foreach ([Scope::DE, Scope::AT, Scope::CH, Scope::Border] as $scope) {
            yield $scope->value => [$scope];
        }
    }

    /** The recordings of the source give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope): void
    {
        $snapshot = (new LayerHarness())->record(LayerId::from('pollen'), $scope, self::RECORDED_NOW);

        self::assertNotSame([], $snapshot->items);
        self::assertContainsOnlyInstancesOf(ModelValueItem::class, $snapshot->items);
    }

    /** Every place of a country lies in one of its regions, the places of the border zone in none. */
    public function testPlacesLieInTheirRegions(): void
    {
        $harness = new LayerHarness();
        foreach ($harness->snapshot(LayerId::from('pollen'), Scope::AT, self::RECORDED_NOW)->items as $item) {
            self::assertCount(1, $item->common()->regionIds, $item->common()->id);
        }
        foreach ($harness->snapshot(LayerId::from('pollen'), Scope::Border, self::RECORDED_NOW)->items as $item) {
            self::assertSame([], $item->common()->regionIds, $item->common()->id);
        }
    }

    /** Outside the pollen season the model has no values: the layer is empty, not failed. */
    public function testOutsideTheSeasonTheLayerIsEmptyNotFailed(): void
    {
        $harness = new LayerHarness();
        $empty = ['current' => ['time' => '2026-01-10T12:00', 'alder_pollen' => null, 'birch_pollen' => null, 'grass_pollen' => null, 'mugwort_pollen' => null, 'olive_pollen' => null, 'ragweed_pollen' => null]];
        $harness->http->respond('https://air-quality-api.open-meteo.com/', (string) json_encode(array_fill(0, 9, $empty)));

        $snapshot = $harness->snapshot(LayerId::from('pollen'), Scope::AT, self::RECORDED_NOW);

        self::assertSame(FeedStatus::Ok, $snapshot->status);
        self::assertSame([], $snapshot->items);
    }
}
